<?php

namespace App\Services;

use App\Models\AIReview;
use App\Models\TestResult;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Main orchestrator for AI Review processing
 * Coordinates data compilation, AI API calls, and result storage
 */
class AIReviewService
{
    protected $compiler;
    protected $apiClient;
    protected $htmlGenerator;
    protected $apiTokenService;
    protected $logChannel;

    public function __construct(
        TestResultCompilerService $compiler,
        AIApiClient $apiClient,
        ReviewHtmlGenerator $htmlGenerator,
        ApiTokenService $apiTokenService
    ) {
        $this->compiler = $compiler;
        $this->apiClient = $apiClient;
        $this->htmlGenerator = $htmlGenerator;
        $this->apiTokenService = $apiTokenService;
        $this->logChannel = $this->determineLogChannel();
    }

    /**
     * Determine the appropriate log channel based on context
     */
    protected function determineLogChannel(): string
    {
        // If we're in a queue job context, use job channel
        if (app()->bound('queue.job')) {
            return 'job';
        }

        // If it's an ODB API request, use odb-log channel
        if (request()->is('api/odb/*')) {
            return 'odb-log';
        }

        // Default to standard logging
        return config('logging.default');
    }

    /**
     * Process single test result for AI review
     * Used by PanelResultsController
     *
     * @param int $testResultId The test result ID to process
     * @return AIReviewResult The processing result
     */
    public function processSingle(int $testResultId): AIReviewResult
    {
        $token = $this->getToken();
        $compiledData = [];

        try {
            return DB::transaction(function () use ($testResultId, $token, &$compiledData) {
                // Step 1: Fetch and compile test result data
                $testResult = $this->compiler->fetchTestResult($testResultId);
                $compiledData = $this->compiler->compileTestResultData($testResult);

                // Step 2: Call AI API
                $aiResponse = $this->apiClient->analyze($compiledData, $token);

                // Step 3: Convert response to HTML
                $htmlReview = $this->htmlGenerator->convertToHtml($aiResponse['ai_analysis']['answer']);

                // Step 4: Store review
                $this->storeReview($testResult, $compiledData, $aiResponse, $htmlReview);

                Log::channel($this->logChannel)->info('AI review processed successfully', [
                    'test_result_id' => $testResult->id
                ]);

                return new AIReviewResult(
                    $testResult->id,
                    $htmlReview,
                    true,
                    null,
                    $testResult->patient->icno ?? null,
                    $testResult->ref_id ?? null
                );
            });
        } catch (Exception $e) {
            Log::channel($this->logChannel)->error('Failed to process AI review - skipping database storage', [
                'test_result_id' => $testResultId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new AIReviewResult(
                $testResultId,
                null,
                false,
                $e->getMessage()
            );
        }
    }

    /**
     * Process multiple test results in bulk
     * Used by BloodTestController for ODB bulk processing
     * Optimized with split transactions to avoid long locks
     *
     * @param array $items Array of items with 'icno' and optional 'refid'
     * @return array Array of AIReviewResult objects
     */
    public function processBulk(array $items): array
    {
        $token = $this->getToken();
        $results = [];
        $processingStartTime = now();
        
        // Step 1: Fetch all test results in one transaction (fast)
        $testResultsData = DB::transaction(function () use ($items) {
            return $this->compiler->fetchBulkTestResults($items);
        });

        Log::channel($this->logChannel)->info('Bulk AI review started', [
            'total_items' => count($items),
            'fetched_results' => count($testResultsData)
        ]);

        // Step 2: Process AI API calls OUTSIDE transaction (no DB locks during HTTP calls)
        foreach ($testResultsData as $data) {
            try {
                // Call AI API (can take up to 120 seconds)
                $aiResponse = $this->apiClient->analyze($data['compiled_data'], $token);

                // Convert to HTML
                $htmlReview = $this->htmlGenerator->convertToHtml($aiResponse['ai_analysis']['answer']);

                // Step 3: Store in separate transaction (fast)
                DB::transaction(function () use ($data, $aiResponse, $htmlReview) {
                    $this->storeReview($data['test_result'], $data['compiled_data'], $aiResponse, $htmlReview);
                });

                Log::channel($this->logChannel)->info('Bulk item processed successfully', [
                    'test_result_id' => $data['test_result']->id,
                    'icno' => $data['icno']
                ]);

                $results[] = new AIReviewResult(
                    $data['test_result']->id,
                    $htmlReview,
                    true,
                    null,
                    $data['icno'],
                    $data['refid']
                );
            } catch (Exception $e) {
                Log::channel($this->logChannel)->error('Failed to process bulk item - skipping database storage', [
                    'test_result_id' => $data['test_result']->id ?? 'unknown',
                    'icno' => $data['icno'],
                    'error' => $e->getMessage()
                ]);

                $results[] = new AIReviewResult(
                    $data['test_result']->id ?? 0,
                    null,
                    false,
                    $e->getMessage(),
                    $data['icno'],
                    $data['refid']
                );
            }
        }

        $processingTime = now()->diffInSeconds($processingStartTime);
        Log::channel($this->logChannel)->info('Bulk AI review completed', [
            'total_items' => count($items),
            'processed_results' => count($results),
            'successful' => count(array_filter($results, fn($r) => $r->isSuccessful())),
            'failed' => count(array_filter($results, fn($r) => $r->isFailed())),
            'processing_time_seconds' => $processingTime
        ]);

        return $results;
    }

    /**
     * Store AI review to database
     *
     * @param TestResult $testResult The test result being reviewed
     * @param array $compiledData The compiled test data sent to AI
     * @param array $aiResponse The full AI API response
     * @param string $htmlReview The HTML formatted review
     */
    protected function storeReview(TestResult $testResult, array $compiledData, array $aiResponse, string $htmlReview): void
    {
        AIReview::updateOrCreate(
            ['test_result_id' => $testResult->id],
            [
                'compiled_results' => $compiledData,
                'http_status' => $aiResponse['ai_analysis']['status'],
                'ai_response' => $htmlReview,
                'raw_response' => $aiResponse,
                'error_message' => null,
                'is_successful' => true
            ]
        );

        $testResult->is_reviewed = true;
        $testResult->save();

        Log::channel($this->logChannel)->info('AI review stored and test result marked as reviewed', [
            'test_result_id' => $testResult->id
        ]);
    }

    /**
     * Get valid authentication token
     *
     * @return string The authentication token
     * @throws RuntimeException If token cannot be obtained
     */
    protected function getToken(): string
    {
        $token = $this->apiTokenService->getValidToken();

        if (!$token) {
            Log::channel($this->logChannel)->error('Failed to obtain AI service token');
            throw new RuntimeException('Failed to obtain AI service token');
        }

        Log::channel($this->logChannel)->info('AI service token obtained successfully');

        return $token;
    }
}
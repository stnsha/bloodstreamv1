<?php

namespace App\Jobs\Testing;

use App\Models\AIError;
use App\Models\AIReview;
use App\Models\TestResult;
use App\Services\ApiTokenService;
use App\Services\TestResultCompilerService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SendToAIServerTesting implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;
    public $backoff = [60, 300, 900];
    public $testResultId;

    public function __construct(int $testResultId)
    {
        $this->testResultId = $testResultId;
        $this->onQueue('ai-reviews-testing');
    }

    public function handle(
        TestResultCompilerService $compiler,
        ApiTokenService $apiTokenService
    ): void {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        Log::info('SendToAIServerTesting: Starting AI dispatch', [
            'test_result_id' => $this->testResultId,
            'attempt' => $this->attempts(),
        ]);

        try {
            $testResult = TestResult::find($this->testResultId);

            if (! $testResult) {
                Log::info('SendToAIServerTesting: Test result not found, skipping', [
                    'test_result_id' => $this->testResultId,
                ]);

                return;
            }

            if ($testResult->is_reviewed) {
                Log::info('SendToAIServerTesting: Test result already reviewed, skipping', [
                    'test_result_id' => $this->testResultId,
                ]);

                return;
            }

            $token = $apiTokenService->getValidToken();

            if (! $token) {
                throw new RuntimeException('Failed to obtain AI service token');
            }

            $testResult = $compiler->fetchTestResult($this->testResultId);
            $compiledData = $compiler->compileTestResultData($testResult, 'LOCAL_TESTING');

            $aiReview = DB::transaction(function () use ($compiledData) {
                return AIReview::updateOrCreate(
                    ['test_result_id' => $this->testResultId],
                    [
                        'processing_status' => 'PENDING',
                        'compiled_results' => $compiledData,
                        'ai_response' => null,
                        'raw_response' => null,
                    ]
                );
            });

            $payload = array_merge([
                'test_result_id' => $this->testResultId,
                'source' => 'LOCAL_TESTING',
            ], $compiledData);

            $testingUrl = config('credentials.ai_review.testing');

            if (empty($testingUrl)) {
                throw new RuntimeException('AI_REVIEW_TESTING URL is not configured');
            }

            $response = Http::timeout(30)
                ->withToken($token)
                ->post($testingUrl, $payload);

            if ($response->failed()) {
                $aiReview->update([
                    'processing_status' => 'FAILED',
                    'raw_response' => $response->body(),
                ]);

                AIError::create([
                    'test_result_id' => $this->testResultId,
                    'processing_status' => 'FAILED',
                    'http_status' => $response->status(),
                    'error_message' => 'AI testing server returned HTTP ' . $response->status(),
                    'compiled_data' => $compiledData,
                    'attempt_count' => $this->attempts(),
                ]);

                throw new RuntimeException('AI testing server returned HTTP ' . $response->status());
            }

            $responseData = $response->json();

            if (isset($responseData['success']) && $responseData['success'] === false) {
                $aiReview->update([
                    'processing_status' => 'FAILED',
                    'raw_response' => json_encode($responseData),
                ]);

                AIError::create([
                    'test_result_id' => $this->testResultId,
                    'processing_status' => 'FAILED',
                    'http_status' => 500,
                    'error_message' => $responseData['message'] ?? $responseData['error'] ?? 'AI testing server rejected request',
                    'compiled_data' => $compiledData,
                    'attempt_count' => $this->attempts(),
                ]);

                return;
            }

            $aiReview->update([
                'processing_status' => $responseData['status'] ?? 'QUEUED',
            ]);

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $memoryUsed = round((memory_get_usage() - $startMemory) / 1024 / 1024, 2);

            Log::info('SendToAIServerTesting: AI review sent successfully', [
                'test_result_id' => $this->testResultId,
                'url' => $testingUrl,
                'source' => 'LOCAL_TESTING',
                'duration_ms' => $duration,
                'memory_mb' => $memoryUsed,
                'attempt' => $this->attempts(),
            ]);
        } catch (Exception $e) {
            Log::error('SendToAIServerTesting: Failed to send to AI server', [
                'test_result_id' => $this->testResultId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendToAIServerTesting: Job failed permanently after all retries', [
            'test_result_id' => $this->testResultId,
            'error' => $exception->getMessage(),
        ]);

        try {
            DB::transaction(function () use ($exception) {
                $aiReview = AIReview::where('test_result_id', $this->testResultId)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($aiReview) {
                    $aiReview->update([
                        'processing_status' => 'FAILED',
                        'raw_response' => json_encode([
                            'error' => $exception->getMessage(),
                            'source' => 'LOCAL_TESTING',
                            'all_retries_exhausted' => true,
                        ]),
                    ]);
                }

                AIError::create([
                    'test_result_id' => $this->testResultId,
                    'processing_status' => 'FAILED',
                    'http_status' => 500,
                    'error_message' => $exception->getMessage(),
                    'error_trace' => $exception->getTraceAsString(),
                    'compiled_data' => $aiReview->compiled_results ?? null,
                    'attempt_count' => $this->tries,
                ]);
            });
        } catch (Exception $dbError) {
            Log::error('SendToAIServerTesting: Failed to record job failure', [
                'test_result_id' => $this->testResultId,
                'error' => $dbError->getMessage(),
            ]);
        }
    }
}

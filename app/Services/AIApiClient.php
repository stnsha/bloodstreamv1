<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AIApiClient
{
    protected $logChannel;

    public function __construct()
    {
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
     * Analyze compiled test result data via AI API
     * Automatically retries on connection failures (3 attempts with exponential backoff)
     *
     * @param array $compiledData The compiled test result data
     * @param string $token The authentication token
     * @return array The AI response data
     * @throws RuntimeException If the API call fails or returns an error status
     */
    public function analyze(array $compiledData, string $token): array
    {
        Log::channel($this->logChannel)->info('Calling AI analysis API', [
            'data_keys' => array_keys($compiledData)
        ]);

        $response = Http::timeout(120)
            ->retry(3, 1000, function ($exception) {
                // Retry only on connection exceptions, not on HTTP 4xx/5xx responses
                return $exception instanceof \Illuminate\Http\Client\ConnectionException;
            })
            ->withToken($token)
            ->post(config('credentials.ai_review.analysis'), $compiledData);

        if ($response->failed()) {
            $responseBody = $response->body();

            Log::channel($this->logChannel)->error('AI analysis API call failed', [
                'response_status' => $response->status(),
                'response_body' => $responseBody
            ]);

            throw new RuntimeException(
                "AI analysis API call failed with status {$response->status()}. Response: " . $responseBody
            );
        }

        $responseData = $response->json();

        // Validate response structure and status
        if (!isset($responseData['ai_analysis'])) {
            $errorDetails = json_encode($responseData);

            Log::channel($this->logChannel)->error('Invalid AI response structure', [
                'response' => $responseData
            ]);

            throw new RuntimeException('Invalid AI response structure: missing ai_analysis key. Response: ' . $errorDetails);
        }

        if (!($responseData['ai_analysis']['success'] ?? false)
            || ($responseData['ai_analysis']['status'] ?? 500) != 200) {
            $errorDetails = json_encode($responseData);

            Log::channel($this->logChannel)->error('AI analysis returned error status', [
                'response' => $responseData
            ]);

            throw new RuntimeException('AI analysis returned error status. Response: ' . $errorDetails);
        }

        Log::channel($this->logChannel)->info('AI analysis successful');

        return $responseData;
    }

    /**
     * Send test result data to AI server asynchronously (webhook-based)
     * Retries up to 3 times with exponential backoff on non-429 failures.
     * On 429 (QUEUE_FULL), throws immediately so job-level retry handles it.
     *
     * @param array $payload The payload to send (includes test_result_id, source, and compiled data)
     * @param string $token The authentication token
     * @return array The AI server response data (status)
     * @throws RuntimeException If the API call fails after all retries or on 429
     */
    public function sendAsync(array $payload, string $token): array
    {
        $maxRetries = 3;
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                $response = Http::timeout(30)
                    ->withToken($token)
                    ->post(config('credentials.ai_review.analysis'), $payload);

                if ($response->failed()) {
                    $responseBody = $response->body();

                    // 429/QUEUE_FULL: throw immediately, let job-level retry handle it
                    if ($response->status() === 429) {
                        Log::channel($this->logChannel)->warning('AI server queue full (429), skipping HTTP retries', [
                            'attempt' => $attempt + 1,
                            'response_body' => $responseBody,
                        ]);

                        throw new RuntimeException(
                            "AI server returned 429 QUEUE_FULL. Response: " . $responseBody
                        );
                    }

                    $attempt++;

                    Log::channel($this->logChannel)->warning('AI async send failed, may retry', [
                        'attempt' => $attempt,
                        'status' => $response->status(),
                        'will_retry' => $attempt < $maxRetries
                    ]);

                    if ($attempt < $maxRetries) {
                        // Exponential backoff: 1s, 2s, 4s
                        $backoffSeconds = (2 ** ($attempt - 1));
                        sleep($backoffSeconds);
                        continue;
                    }

                    throw new RuntimeException(
                        "Failed to send data to AI server after {$maxRetries} attempts - status {$response->status()}. Response: " . $responseBody
                    );
                }

                // Success
                return $response->json();

            } catch (Exception $e) {
                $attempt++;
                $lastException = $e;

                if ($attempt < $maxRetries) {
                    Log::channel($this->logChannel)->warning('AI async send exception, retrying', [
                        'attempt' => $attempt,
                        'error' => $e->getMessage()
                    ]);
                    // Exponential backoff
                    $backoffSeconds = (2 ** ($attempt - 1));
                    sleep($backoffSeconds);
                    continue;
                }

                throw new RuntimeException(
                    "Failed to send data to AI server after {$maxRetries} attempts: " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        // Should never reach here, but safety fallback
        throw $lastException ?? new RuntimeException("AI async send failed unexpectedly");
    }
}
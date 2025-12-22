<?php

namespace App\Services;

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
     *
     * @param array $payload The payload to send (includes test_result_id, source, and compiled data)
     * @param string $token The authentication token
     * @return array The AI server response data (status)
     * @throws RuntimeException If the API call fails
     */
    public function sendAsync(array $payload, string $token): array
    {
        $response = Http::timeout(30)
            ->withToken($token)
            ->post(config('credentials.ai_review.testing'), $payload); //change to analysis after done testing

        if ($response->failed()) {
            $responseBody = $response->body();

            // throw new RuntimeException(
            //     "Failed to send data to AI server - status {$response->status()}. Response: " . $responseBody
            // );
        }

        $responseData = $response->json();

        return $responseData;
    }
}
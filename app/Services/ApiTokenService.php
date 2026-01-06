<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ApiTokenService
{
    private const TOKEN_CACHE_KEY = 'ai_api_token';
    private const TOKEN_EXPIRY_DAYS = 30;
    
    /**
     * Get valid API token, refresh if expired or missing
     */
    public function getValidToken(): ?string
    {
        // Try to get cached token first
        $token = Cache::get(self::TOKEN_CACHE_KEY);
        
        if ($token) {
            // Log::info('Using cached API token');
            return $token;
        }
        
        // Token not found or expired, get new one
        return $this->refreshToken();
    }
    
    /**
     * Force refresh the API token
     * Retries up to 3 times with exponential backoff on connection failures
     */
    public function refreshToken(): ?string
    {
        $maxRetries = 3;
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                $attempt++;
                Log::info('ApiTokenService: Attempting to refresh AI API token', ['attempt' => $attempt]);

                $response = Http::timeout(60)
                    ->retry(2, 1000, function ($exception) {
                        return $exception instanceof \Illuminate\Http\Client\ConnectionException;
                    })
                    ->post(config('credentials.ai_review.login'), [
                        "username" => config('credentials.odb.username'),
                        "password" => config('credentials.odb.password')
                    ]);

                if ($response->failed()) {
                    $attempt++;
                    Log::warning('ApiTokenService: API token refresh failed', [
                        'attempt' => $attempt,
                        'status' => $response->status(),
                        'will_retry' => $attempt < $maxRetries
                    ]);

                    if ($attempt < $maxRetries) {
                        $backoffSeconds = (2 ** ($attempt - 2));
                        sleep($backoffSeconds);
                        continue;
                    }

                    return $this->getBackupToken();
                }

                $responseData = $response->json();
                $token = $responseData['token'] ?? null;

                if (!$token) {
                    Log::error('ApiTokenService: No token in API response', ['response' => $responseData]);
                    return $this->getBackupToken();
                }

                // Cache the new token and also store as backup
                Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addDays(self::TOKEN_EXPIRY_DAYS));
                Cache::put('ai_token_backup', $token, now()->addDays(1)); // Backup for 1 day

                Log::info('ApiTokenService: API token refreshed and cached successfully');
                return $token;

            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;

                Log::warning('ApiTokenService: Exception during API token refresh', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'will_retry' => $attempt < $maxRetries
                ]);

                if ($attempt < $maxRetries) {
                    $backoffSeconds = (2 ** ($attempt - 2));
                    sleep($backoffSeconds);
                    continue;
                }

                return $this->getBackupToken();
            }
        }

        // Final fallback
        return $this->getBackupToken();
    }

    /**
     * Get backup token from cache (used when token service is unavailable)
     */
    private function getBackupToken(): ?string
    {
        $backup = Cache::get('ai_token_backup');
        if ($backup) {
            Log::warning('ApiTokenService: Using backup token due to refresh failure');
            return $backup;
        }

        Log::error('ApiTokenService: Unable to refresh token and no backup available');
        return null;
    }
    
    /**
     * Clear cached token (useful for testing or force refresh)
     */
    public function clearToken(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
        Log::info('ApiTokenService: API token cache cleared');
    }
    
    /**
     * Check if token exists in cache
     */
    public function hasValidToken(): bool
    {
        return Cache::has(self::TOKEN_CACHE_KEY);
    }
    
    /**
     * Get token expiry time
     */
    public function getTokenExpiry(): ?string
    {
        if (!$this->hasValidToken()) {
            return null;
        }
        
        // This is an estimation since we don't store expiry separately
        // Redis doesn't provide TTL through Laravel Cache facade easily
        return 'Token cached for ' . self::TOKEN_EXPIRY_DAYS . ' days from creation';
    }
}
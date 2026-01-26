<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TokenValidationRateLimiter
{
    const MAX_ATTEMPTS = 50;       // Allow more attempts for high-volume systems

    const WINDOW_SECONDS = 60;     // 1 minute window (matches route throttle)

    const CACHE_PREFIX = 'token_validation_fail:';

    /**
     * Check if client has exceeded rate limit for this error pattern.
     *
     * @param  string  $ip  Client IP address
     * @param  string  $errorPattern  Error type (placeholder, invalid_format, etc.)
     * @return array Rate limit check result
     */
    public function checkRateLimit(string $ip, string $errorPattern): array
    {
        $cacheKey = $this->getCacheKey($ip, $errorPattern);
        $data = Cache::get($cacheKey);

        // Handle both old format (integer) and new format (array)
        if (is_array($data)) {
            $attempts = (int) ($data['attempts'] ?? 0);
            $expiresAt = $data['expires_at'] ?? null;
        } else {
            $attempts = (int) ($data ?? 0);
            $expiresAt = null;
        }

        if ($attempts >= self::MAX_ATTEMPTS) {
            // Calculate retry_after from stored expiration or use default window
            if ($expiresAt) {
                $retryAfter = max(1, $expiresAt - time());
            } else {
                $retryAfter = self::WINDOW_SECONDS;
            }

            Log::warning('Token validation rate limit exceeded', [
                'ip' => $ip,
                'error_pattern' => $errorPattern,
                'attempts' => $attempts,
                'max_attempts' => self::MAX_ATTEMPTS,
                'retry_after' => $retryAfter,
            ]);

            return [
                'allowed' => false,
                'attempts' => $attempts,
                'max_attempts' => self::MAX_ATTEMPTS,
                'retry_after' => $retryAfter,
            ];
        }

        return [
            'allowed' => true,
            'attempts' => $attempts,
            'max_attempts' => self::MAX_ATTEMPTS,
            'retry_after' => null,
        ];
    }

    /**
     * Record a validation failure for this IP and error pattern.
     *
     * @param  string  $ip  Client IP address
     * @param  string  $errorPattern  Error type
     */
    public function recordFailure(string $ip, string $errorPattern): void
    {
        $cacheKey = $this->getCacheKey($ip, $errorPattern);
        $data = Cache::get($cacheKey);

        // Handle both old format (integer) and new format (array)
        if (is_array($data)) {
            $attempts = (int) ($data['attempts'] ?? 0);
        } else {
            $attempts = (int) ($data ?? 0);
        }

        // Store as array with expiration timestamp for reliable TTL calculation
        Cache::put($cacheKey, [
            'attempts' => $attempts + 1,
            'expires_at' => time() + self::WINDOW_SECONDS,
        ], self::WINDOW_SECONDS);
    }

    /**
     * Get cache key for this IP and pattern combination.
     *
     * @param  string  $ip  Client IP address
     * @param  string  $errorPattern  Error type
     * @return string Cache key
     */
    private function getCacheKey(string $ip, string $errorPattern): string
    {
        return self::CACHE_PREFIX.$ip.':'.$errorPattern;
    }
}

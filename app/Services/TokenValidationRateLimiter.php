<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class TokenValidationRateLimiter
{
    const MAX_ATTEMPTS = 5;
    const WINDOW_SECONDS = 300; // 5 minutes
    const CACHE_PREFIX = 'token_validation_fail:';

    /**
     * Check if client has exceeded rate limit for this error pattern.
     *
     * @param string $ip Client IP address
     * @param string $errorPattern Error type (placeholder, invalid_format, etc.)
     * @return array Rate limit check result
     */
    public function checkRateLimit(string $ip, string $errorPattern): array
    {
        $cacheKey = $this->getCacheKey($ip, $errorPattern);
        $attempts = (int) Cache::get($cacheKey, 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            $ttl = Cache::getStore()->connection()->ttl($cacheKey) ?? self::WINDOW_SECONDS;
            $retryAfter = max(1, $ttl);

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
     * @param string $ip Client IP address
     * @param string $errorPattern Error type
     * @return void
     */
    public function recordFailure(string $ip, string $errorPattern): void
    {
        $cacheKey = $this->getCacheKey($ip, $errorPattern);
        $attempts = (int) Cache::get($cacheKey, 0);

        Cache::put($cacheKey, $attempts + 1, self::WINDOW_SECONDS);
    }

    /**
     * Get cache key for this IP and pattern combination.
     *
     * @param string $ip Client IP address
     * @param string $errorPattern Error type
     * @return string Cache key
     */
    private function getCacheKey(string $ip, string $errorPattern): string
    {
        return self::CACHE_PREFIX . $ip . ':' . $errorPattern;
    }
}

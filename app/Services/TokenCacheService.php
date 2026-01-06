<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\LabCredential;

class TokenCacheService
{
    const CACHE_PREFIX = 'auth_token_cache:';
    const CACHE_TTL_SECONDS = 2592000; // 30 days (same as JWT)

    /**
     * Store authenticated token in server-side cache.
     * Used for timeout mitigation when client can retry same login.
     *
     * @param string $ip Client IP address
     * @param string $username Lab credential username
     * @param string $token JWT token
     * @param int $expiresIn Expiration time in seconds
     * @param int $labCredentialId Lab credential ID
     * @param int $labId Lab ID
     * @return void
     */
    public function cacheToken(
        string $ip,
        string $username,
        string $token,
        int $expiresIn,
        int $labCredentialId,
        int $labId
    ): void {
        $cacheKey = $this->getCacheKey($ip, $username);

        $cacheData = [
            'token' => $token,
            'username' => $username,
            'lab_credential_id' => $labCredentialId,
            'lab_id' => $labId,
            'issued_at' => now()->toDateTimeString(),
            'expires_at' => $expiresIn,
            'ip' => $ip,
        ];

        Cache::put($cacheKey, $cacheData, self::CACHE_TTL_SECONDS);

        Log::channel('auth')->info('Token cached successfully', [
            'username' => $username,
            'ip' => $ip,
            'ttl' => self::CACHE_TTL_SECONDS,
            'cache_key' => $cacheKey,
        ]);
    }

    /**
     * Retrieve cached token for a client.
     * Returns null if no cache exists.
     *
     * @param string $ip Client IP address
     * @param string $username Lab credential username
     * @return array|null Cached token data or null
     */
    public function getCachedToken(string $ip, string $username): ?array
    {
        $cacheKey = $this->getCacheKey($ip, $username);
        $cached = Cache::get($cacheKey);

        if ($cached) {
            Log::channel('auth')->info('Cached token found', [
                'username' => $username,
                'ip' => $ip,
                'issued_at' => $cached['issued_at'],
            ]);
            return $cached;
        }

        Log::channel('auth')->debug('Cached token not found', [
            'username' => $username,
            'ip' => $ip,
        ]);

        return null;
    }

    /**
     * Validate if cached token is still usable.
     * Checks:
     * 1. Token exists in cache
     * 2. Token has not expired
     * 3. User is still active in database
     *
     * @param array $cachedData Cached token data
     * @return bool True if valid and usable
     */
    public function validateCachedToken(array $cachedData): bool
    {
        // Check 1: Verify required fields exist
        if (empty($cachedData['token']) || empty($cachedData['lab_credential_id'])) {
            Log::channel('auth')->warning('Cached token missing required fields', [
                'username' => $cachedData['username'] ?? 'unknown',
                'has_token' => !empty($cachedData['token']),
                'has_credential_id' => !empty($cachedData['lab_credential_id']),
            ]);
            return false;
        }

        // Check 2: Verify expiration time
        if (!empty($cachedData['expires_at'])) {
            $expiresAt = $cachedData['expires_at'];
            // expires_at is stored in seconds (TTL format)
            if ($expiresAt <= 0) {
                Log::channel('auth')->info('Cached token expired', [
                    'username' => $cachedData['username'],
                    'expires_at' => $expiresAt,
                ]);
                return false;
            }
        }

        // Check 3: Verify user is still active in database
        $labCredential = LabCredential::find($cachedData['lab_credential_id']);

        if (!$labCredential) {
            Log::channel('auth')->warning('Cached token credential not found', [
                'username' => $cachedData['username'],
                'lab_credential_id' => $cachedData['lab_credential_id'],
            ]);
            return false;
        }

        if (!$labCredential->is_active) {
            Log::channel('auth')->warning('Cached token user is inactive', [
                'username' => $cachedData['username'],
                'lab_credential_id' => $cachedData['lab_credential_id'],
            ]);
            return false;
        }

        // Check 4: Verify soft delete not applied
        if ($labCredential->trashed()) {
            Log::channel('auth')->warning('Cached token user is soft-deleted', [
                'username' => $cachedData['username'],
                'lab_credential_id' => $cachedData['lab_credential_id'],
            ]);
            return false;
        }

        Log::channel('auth')->debug('Cached token validated successfully', [
            'username' => $cachedData['username'],
            'lab_credential_id' => $cachedData['lab_credential_id'],
        ]);

        return true;
    }

    /**
     * Invalidate cached token for a client.
     * Called on logout or when token should be refreshed.
     *
     * @param string $ip Client IP address
     * @param string $username Lab credential username
     * @return void
     */
    public function invalidateCache(string $ip, string $username): void
    {
        $cacheKey = $this->getCacheKey($ip, $username);
        Cache::forget($cacheKey);

        Log::channel('auth')->info('Cache invalidated', [
            'username' => $username,
            'ip' => $ip,
            'cache_key' => $cacheKey,
        ]);
    }

    /**
     * Generate cache key from IP and username.
     *
     * @param string $ip Client IP address
     * @param string $username Lab credential username
     * @return string Cache key
     */
    private function getCacheKey(string $ip, string $username): string
    {
        return self::CACHE_PREFIX . $ip . ':' . $username;
    }

    /**
     * Clear all cache entries (useful for testing or admin operations).
     * Warning: This clears ALL cached tokens!
     *
     * @return void
     */
    public function clearAllCache(): void
    {
        Cache::flush();
        Log::channel('auth')->warning('All token cache cleared');
    }

    /**
     * Get cache statistics (for monitoring).
     *
     * @return array Statistics about cache usage
     */
    public function getCacheStats(): array
    {
        // Note: File cache driver doesn't provide direct stats
        // This is a placeholder for future Redis implementation
        return [
            'cache_driver' => config('cache.default'),
            'cache_location' => storage_path('framework/cache/data'),
            'message' => 'File-based cache. For detailed stats, use Redis backend.',
        ];
    }
}

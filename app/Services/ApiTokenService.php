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
            Log::info('Using cached API token');
            return $token;
        }
        
        // Token not found or expired, get new one
        return $this->refreshToken();
    }
    
    /**
     * Force refresh the API token
     */
    public function refreshToken(): ?string
    {
        try {
            Log::info('Attempting to refresh AI API token');

            $response = Http::timeout(60)->post(config('credentials.ai_review.login'), [
                "username" => config('credentials.odb.username'),
                "password" => config('credentials.odb.password')
            ]);
            
            if ($response->failed()) {
                Log::error('API token refresh failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return null;
            }
            
            $responseData = $response->json();
            $token = $responseData['token'] ?? null;
            
            if (!$token) {
                Log::error('No token in API response', ['response' => $responseData]);
                return null;
            }
            
            // Cache the new token for 30 days
            Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addDays(self::TOKEN_EXPIRY_DAYS));
            
            Log::info('API token refreshed and cached successfully');
            return $token;
            
        } catch (Exception $e) {
            Log::error('Exception during API token refresh', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
    
    /**
     * Clear cached token (useful for testing or force refresh)
     */
    public function clearToken(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
        Log::info('API token cache cleared');
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
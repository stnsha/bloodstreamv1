<?php

namespace App\Http\Middleware;

use App\Services\TokenValidationRateLimiter;
use App\Services\TokenValidationService;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class APIAuthMiddleware
{
    /**
     * Log raw authentication data for debugging failures.
     * TEMPORARY: Remove after resolving TokenInvalidException issue.
     *
     * @param Request $request
     * @param array $context Additional context to merge
     * @return void
     */
    private function logRawAuthForDebug(Request $request, array $context = []): void
    {
        Log::channel('auth')->warning('DEBUG_RAW_AUTH_FAILURE', array_merge([
            'raw_authorization_header' => $request->header('Authorization'),
            'raw_bearer_token' => $request->bearerToken(),
            'raw_cookie_token' => $request->cookie('jwt_token'),
            'content_type' => $request->header('Content-Type'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint' => $request->fullUrl(),
            'method' => $request->method(),
        ], $context));
    }

    public function handle(Request $request, Closure $next)
    {
        $logContext = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint' => $request->fullUrl(),
            'method' => $request->method(),
        ];

        try {
            // Get token from request
            $token = $request->bearerToken() ?? $request->cookie('jwt_token');
            $tokenSource = $request->bearerToken() ? 'bearer' : ($request->cookie('jwt_token') ? 'cookie' : 'none');

            if (!$token) {
                $this->logRawAuthForDebug($request, ['failure_reason' => 'no_token_provided']);
                Log::channel('auth')->warning('Authentication failed: No token provided', $logContext);
                return response()->json(['error' => 'Unauthorized. Token is required.'], 401);
            }

            // Validate token format before attempting expensive JWT parsing
            $validationService = new TokenValidationService();
            $validation = $validationService->validateTokenFormat($token);

            if (!$validation['valid']) {
                // Check rate limiting for this error pattern
                $rateLimiter = new TokenValidationRateLimiter();
                $clientIp = $request->ip();
                $errorType = $validation['error_type'];
                $rateLimitCheck = $rateLimiter->checkRateLimit($clientIp, $errorType);

                // Log validation failure
                $this->logRawAuthForDebug($request, [
                    'failure_reason' => 'invalid_token_format',
                    'error_type' => $errorType,
                    'pattern_matched' => $validation['pattern_matched'],
                    'token_value' => $validation['should_log_full_token'] ? $token : 'REDACTED',
                    'token_length' => strlen($token),
                    'segment_count' => substr_count($token, '.') + 1,
                ]);

                // If rate limited, return 429
                if (!$rateLimitCheck['allowed']) {
                    Log::channel('auth')->warning('Authentication failed: Rate limit exceeded', array_merge($logContext, [
                        'error_type' => $errorType,
                        'attempts' => $rateLimitCheck['attempts'],
                        'retry_after' => $rateLimitCheck['retry_after'],
                    ]));

                    return response()->json([
                        'error' => 'Too many invalid token attempts',
                        'error_type' => 'rate_limit_exceeded',
                        'attempts' => $rateLimitCheck['attempts'],
                        'max_attempts' => $rateLimitCheck['max_attempts'],
                        'retry_after' => $rateLimitCheck['retry_after'],
                        'hint' => 'Too many authentication failures. Please fix your token configuration and try again after ' . $rateLimitCheck['retry_after'] . ' seconds.',
                    ], 429);
                }

                // Record this failure for rate limiting
                $rateLimiter->recordFailure($clientIp, $errorType);

                // Log error with context
                Log::channel('auth')->error('Authentication failed: Invalid token format', array_merge($logContext, [
                    'error_type' => $errorType,
                    'error_message' => $validation['error_message'],
                    'pattern_matched' => $validation['pattern_matched'],
                    'token_length' => strlen($token),
                ]));

                return response()->json([
                    'error' => $validation['error_message'],
                    'error_type' => $errorType,
                    'hint' => $this->getClientHint($errorType),
                ], 401);
            }

            // Log token metadata (never log full token for security)
            $tokenPreview = strlen($token) > 20
                ? substr($token, 0, 10) . '...' . substr($token, -10)
                : 'token_too_short';

            Log::channel('auth')->info('Authentication attempt', array_merge($logContext, [
                'token_source' => $tokenSource,
                'token_length' => strlen($token),
                'token_preview' => $tokenPreview,
            ]));

            // Authenticate using the lab guard with the token
            $user = Auth::guard('lab')->setToken($token)->user();

            if (!$user) {
                $this->logRawAuthForDebug($request, ['failure_reason' => 'user_not_found']);
                Log::channel('auth')->error('Authentication failed: User not found after token validation', array_merge($logContext, [
                    'token_preview' => $tokenPreview,
                    'reason' => 'User returned null from guard',
                ]));
                return response()->json(['error' => 'Invalid token'], 401);
            }

            // Log successful authentication
            Log::channel('auth')->info('Authentication successful', array_merge($logContext, [
                'lab_credential_id' => $user->id,
                'lab_id' => $user->lab_id,
                'username' => $user->username,
            ]));

            // The user is already authenticated by the guard, no need to call setUser again
            // Just proceed to the next middleware/controller
            return $next($request);
        } catch (TokenExpiredException $e) {
            $this->logRawAuthForDebug($request, [
                'failure_reason' => 'token_expired',
                'exception_message' => $e->getMessage(),
            ]);
            Log::channel('auth')->error('Authentication failed: Token expired', array_merge($logContext, [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]));
            return response()->json(['error' => 'Token has expired'], 401);
        } catch (TokenInvalidException $e) {
            $this->logRawAuthForDebug($request, [
                'failure_reason' => 'token_invalid',
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
            ]);
            Log::channel('auth')->error('Authentication failed: Token invalid', array_merge($logContext, [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]));
            return response()->json(['error' => 'Token is invalid'], 401);
        } catch (JWTException $e) {
            $this->logRawAuthForDebug($request, [
                'failure_reason' => 'jwt_exception',
                'exception_message' => $e->getMessage(),
            ]);
            Log::channel('auth')->error('Authentication failed: JWT exception', array_merge($logContext, [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]));
            return response()->json(['error' => 'Token error'], 401);
        } catch (Exception $e) {
            $this->logRawAuthForDebug($request, [
                'failure_reason' => 'general_exception',
                'exception_message' => $e->getMessage(),
            ]);
            Log::channel('auth')->error('Authentication failed: General exception', array_merge($logContext, [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]));
            return response()->json(['error' => 'Authentication failed'], 401);
        }
    }

    /**
     * Get client-friendly hint for token validation error.
     *
     * @param string $errorType
     * @return string
     */
    private function getClientHint(string $errorType): string
    {
        $hints = [
            'placeholder' => 'Check your client configuration - token variable not replaced with actual JWT',
            'invalid_format' => 'Token format is incorrect - ensure proper JWT structure',
            'too_short' => 'Token appears truncated or malformed',
            'invalid_chars' => 'Token contains invalid characters',
            'suspicious_pattern' => 'Check your authentication header format',
        ];

        return $hints[$errorType] ?? 'Please verify your authentication token format';
    }
}
<?php

namespace App\Services;

class TokenValidationService
{
    // Pattern constants for validation
    const PATTERN_VARIABLE_PLACEHOLDER_DOLLAR = '/^\$\{[^}]+\}$/';  // ${...}
    const PATTERN_VARIABLE_PLACEHOLDER_CURLY = '/^\{[^}]+\}$/';     // {...}
    const PATTERN_DOLLAR_VARIABLE = '/^\$[a-zA-Z_][a-zA-Z0-9_]*$/'; // $variable
    const PATTERN_JWT_SEGMENTS = '/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/';

    const MIN_JWT_LENGTH = 20;
    const EXPECTED_JWT_SEGMENTS = 3;

    /**
     * Validate token format before JWT parsing.
     * Detects variable placeholders, invalid formats, and suspicious patterns.
     *
     * @param string $token The token to validate
     * @return array Validation result with keys: valid, error_type, error_message, pattern_matched, should_log_full_token
     */
    public function validateTokenFormat(string $token): array
    {
        // Check 1: Variable placeholders FIRST (safe to log, helps diagnose issues)
        if ($placeholderCheck = $this->checkForPlaceholders($token)) {
            return $this->validationFailure(
                'placeholder',
                'Invalid token: Unresolved variable placeholder detected',
                $placeholderCheck['pattern'],
                true  // Safe to log placeholder
            );
        }

        // Check 2: Token length (skip for very short tokens unless they're placeholders)
        if (strlen($token) < self::MIN_JWT_LENGTH) {
            return $this->validationFailure(
                'too_short',
                'Invalid token: Token length insufficient for valid JWT',
                null,
                false
            );
        }

        // Check 3: Suspicious patterns (before character validation, as these are more specific)
        if ($suspiciousCheck = $this->checkForSuspiciousPatterns($token)) {
            return $this->validationFailure(
                'suspicious_pattern',
                'Invalid token: Suspicious pattern detected',
                $suspiciousCheck['pattern'],
                false
            );
        }

        // Check 4: JWT segment structure
        $segmentCount = substr_count($token, '.') + 1;
        if ($segmentCount !== self::EXPECTED_JWT_SEGMENTS) {
            return $this->validationFailure(
                'invalid_format',
                sprintf(
                    'Invalid token: JWT must have 3 segments (header.payload.signature), found %d segments',
                    $segmentCount
                ),
                sprintf('%d_segments', $segmentCount),
                false
            );
        }

        // Check 5: JWT character set validation
        if (!preg_match(self::PATTERN_JWT_SEGMENTS, $token)) {
            return $this->validationFailure(
                'invalid_chars',
                'Invalid token: Contains characters not allowed in JWT format',
                'invalid_characters',
                false
            );
        }

        // All checks passed
        return [
            'valid' => true,
            'error_type' => null,
            'error_message' => null,
            'pattern_matched' => null,
            'should_log_full_token' => false,
        ];
    }

    /**
     * Check for variable placeholder patterns.
     *
     * @param string $token The token to check
     * @return array|null Detection result or null if no match
     */
    private function checkForPlaceholders(string $token): ?array
    {
        $patterns = [
            'dollar_curly' => self::PATTERN_VARIABLE_PLACEHOLDER_DOLLAR,  // ${...}
            'curly_only' => self::PATTERN_VARIABLE_PLACEHOLDER_CURLY,      // {...}
            'dollar_var' => self::PATTERN_DOLLAR_VARIABLE,                 // $variable
        ];

        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $token)) {
                return [
                    'pattern' => $name,
                    'value' => $token,
                ];
            }
        }

        return null;
    }

    /**
     * Check for suspicious patterns in token.
     *
     * @param string $token The token to check
     * @return array|null Detection result or null if no match
     */
    private function checkForSuspiciousPatterns(string $token): ?array
    {
        // Contains spaces
        if (strpos($token, ' ') !== false) {
            return ['pattern' => 'contains_spaces'];
        }

        // Contains "Bearer" (double header)
        if (stripos($token, 'bearer') !== false) {
            return ['pattern' => 'contains_bearer'];
        }

        // Contains common variable names (case-insensitive)
        $suspiciousWords = ['token', 'authorization', 'access', 'jwt', 'auth'];
        foreach ($suspiciousWords as $word) {
            if (stripos($token, $word) !== false && strlen($token) < 50) {
                return ['pattern' => 'contains_' . $word];
            }
        }

        return null;
    }

    /**
     * Create validation failure result.
     *
     * @param string $errorType The error type identifier
     * @param string $errorMessage Human-readable error message
     * @param string|null $patternMatched The pattern that was matched
     * @param bool $shouldLogFullToken Whether it's safe to log the full token
     * @return array Validation failure result
     */
    private function validationFailure(
        string $errorType,
        string $errorMessage,
        ?string $patternMatched,
        bool $shouldLogFullToken
    ): array {
        return [
            'valid' => false,
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'pattern_matched' => $patternMatched,
            'should_log_full_token' => $shouldLogFullToken,
        ];
    }
}

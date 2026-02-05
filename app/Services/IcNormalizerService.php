<?php

namespace App\Services;

class IcNormalizerService
{
    /**
     * Visual similarity character substitutions.
     * Maps commonly mistyped characters to their intended digit.
     */
    const CHAR_MAP = [
        'O' => '0',
        'o' => '0',
        'I' => '1',
        'l' => '1',
        'S' => '5',
        's' => '5',
        'B' => '8',
        'Z' => '2',
        'G' => '6',
        'g' => '6',
    ];

    /**
     * Normalize an IC number for comparison.
     *
     * Removes separators, applies character substitutions for common
     * OCR/typing errors, and standardizes the format.
     *
     * @param string $ic The IC number to normalize
     * @return string The normalized IC number
     */
    public function normalize(string $ic): string
    {
        // Remove whitespace, dashes, and other separators
        $ic = preg_replace('/[\s\-\.\/]/', '', $ic);

        // Uppercase
        $ic = strtoupper($ic);

        // Apply character substitutions
        $normalized = '';
        $length = strlen($ic);
        for ($i = 0; $i < $length; $i++) {
            $char = $ic[$i];
            if (isset(self::CHAR_MAP[$char])) {
                $normalized .= self::CHAR_MAP[$char];
            } else {
                $normalized .= $char;
            }
        }

        return $normalized;
    }

    /**
     * Extract DOB prefix from Malaysian NRIC.
     *
     * Malaysian NRIC format: YYMMDD-SS-NNNN
     * First 6 digits represent date of birth (YYMMDD).
     *
     * @param string $ic The IC number
     * @return string|null The DOB prefix (6 digits) or null if invalid
     */
    public function extractDobPrefix(string $ic): ?string
    {
        $normalized = $this->normalize($ic);

        if (strlen($normalized) >= 6 && ctype_digit(substr($normalized, 0, 6))) {
            return substr($normalized, 0, 6);
        }

        return null;
    }

    /**
     * Validate that IC DOB prefix matches the given DOB.
     *
     * @param string $ic The IC number to validate
     * @param string|null $dob The date of birth to validate against
     * @return bool True if DOB prefix matches
     */
    public function validateDobPrefix(string $ic, ?string $dob): bool
    {
        if (!$dob || in_array($dob, ['0000-00-00', '00000000', ''], true)) {
            return false;
        }

        $prefix = $this->extractDobPrefix($ic);
        if (!$prefix) {
            return false;
        }

        // Convert DOB to YYMMDD format
        $timestamp = strtotime($dob);
        if ($timestamp === false) {
            return false;
        }

        $dobPrefix = date('ymd', $timestamp);

        return $prefix === $dobPrefix;
    }

    /**
     * Extract state code from Malaysian NRIC.
     *
     * State code is digits 7-8 (positions 6-7 in zero-indexed).
     *
     * @param string $ic The IC number
     * @return string|null The state code (2 digits) or null if invalid
     */
    public function extractStateCode(string $ic): ?string
    {
        $normalized = $this->normalize($ic);

        if (strlen($normalized) >= 8 && ctype_digit(substr($normalized, 6, 2))) {
            return substr($normalized, 6, 2);
        }

        return null;
    }

    /**
     * Extract last 4 digits (sequence number) from Malaysian NRIC.
     *
     * @param string $ic The IC number
     * @return string|null The sequence number (4 digits) or null if invalid
     */
    public function extractSequence(string $ic): ?string
    {
        $normalized = $this->normalize($ic);

        if (strlen($normalized) >= 12) {
            return substr($normalized, -4);
        }

        return null;
    }

    /**
     * Check if the IC appears to be a valid Malaysian NRIC format.
     *
     * @param string $ic The IC number to check
     * @return bool True if appears to be valid NRIC format
     */
    public function isValidNricFormat(string $ic): bool
    {
        $normalized = $this->normalize($ic);

        // Malaysian NRIC is 12 digits
        if (strlen($normalized) !== 12) {
            return false;
        }

        // All characters should be digits
        if (!ctype_digit($normalized)) {
            return false;
        }

        // Basic DOB validation (first 6 digits should form valid date)
        $year = (int) substr($normalized, 0, 2);
        $month = (int) substr($normalized, 2, 2);
        $day = (int) substr($normalized, 4, 2);

        if ($month < 1 || $month > 12) {
            return false;
        }

        if ($day < 1 || $day > 31) {
            return false;
        }

        return true;
    }

    /**
     * Calculate similarity score between two IC numbers using Levenshtein distance.
     *
     * @param string $ic1 First IC number
     * @param string $ic2 Second IC number
     * @return float Similarity score between 0 and 1
     */
    public function calculateSimilarity(string $ic1, string $ic2): float
    {
        $normalized1 = $this->normalize($ic1);
        $normalized2 = $this->normalize($ic2);

        if ($normalized1 === $normalized2) {
            return 1.0;
        }

        $distance = levenshtein($normalized1, $normalized2);
        $maxLen = max(strlen($normalized1), strlen($normalized2));

        if ($maxLen === 0) {
            return 0.0;
        }

        return 1 - ($distance / $maxLen);
    }
}

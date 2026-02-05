<?php

namespace App\Services;

class IcNormalizerService
{
    // Visual similarity substitutions
    const CHAR_MAP = [
        'O' => '0', 'o' => '0',  // Letter O to zero
        'I' => '1', 'l' => '1',  // Letter I/l to one
        'S' => '5', 's' => '5',  // Letter S to five
        'B' => '8',              // Letter B to eight
        'Z' => '2',              // Letter Z to two
        'G' => '6', 'g' => '6',  // Letter G to six
    ];

    /**
     * Normalize an IC number for comparison
     */
    public function normalize(string $ic): string
    {
        // Remove whitespace, dashes, and other separators
        $ic = preg_replace('/[\s\-\.\/]/', '', $ic);

        // Uppercase
        $ic = strtoupper($ic);

        // Apply character substitutions
        $normalized = '';
        for ($i = 0; $i < strlen($ic); $i++) {
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
     * Extract DOB prefix from Malaysian NRIC (first 6 digits = YYMMDD)
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
     * Validate that IC DOB prefix matches the given DOB
     */
    public function validateDobPrefix(string $ic, ?string $dob): bool
    {
        if (!$dob || in_array($dob, ['0000-00-00', '00000000', ''])) {
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
     * Extract state code from Malaysian NRIC (digits 7-8)
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
     * Extract last 4 digits (sequence number)
     */
    public function extractSequence(string $ic): ?string
    {
        $normalized = $this->normalize($ic);

        if (strlen($normalized) >= 12) {
            return substr($normalized, -4);
        }

        return null;
    }
}

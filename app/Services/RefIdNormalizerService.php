<?php

namespace App\Services;

class RefIdNormalizerService
{
    /**
     * Visual similarity character substitutions for numeric portion.
     * Maps commonly mistyped characters to their intended digit.
     */
    const CHAR_MAP = [
        'O' => '0',
        'o' => '0',
        'I' => '1',
        'l' => '1',
        'S' => '5',
        's' => '5',
    ];

    /**
     * Normalize a reference ID for comparison.
     *
     * Reference IDs have format: LAB_CODE + NUMBER (e.g., INN10256)
     * Removes separators, applies character substitutions to numeric portion only.
     *
     * @param string $refId The reference ID to normalize
     * @return string The normalized reference ID
     */
    public function normalize(string $refId): string
    {
        // Remove whitespace, dashes
        $refId = preg_replace('/[\s\-]/', '', $refId);

        // Uppercase
        $refId = strtoupper($refId);

        // Extract prefix and number parts
        if (preg_match('/^([A-Z]+)(.*)$/', $refId, $matches)) {
            $prefix = $matches[1];
            $numberPart = $matches[2];

            // Apply character substitutions to number part only
            $normalizedNumber = '';
            $length = strlen($numberPart);
            for ($i = 0; $i < $length; $i++) {
                $char = $numberPart[$i];
                if (isset(self::CHAR_MAP[$char])) {
                    $normalizedNumber .= self::CHAR_MAP[$char];
                } else {
                    $normalizedNumber .= $char;
                }
            }

            return $prefix . $normalizedNumber;
        }

        return $refId;
    }

    /**
     * Extract lab code prefix from reference ID.
     *
     * @param string $refId The reference ID
     * @return string|null The lab code prefix or null if not found
     */
    public function extractLabCode(string $refId): ?string
    {
        if (preg_match('/^([A-Z]+)\d/', strtoupper($refId), $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract numeric part of reference ID.
     *
     * @param string $refId The reference ID
     * @return string|null The numeric part or null if not found
     */
    public function extractNumber(string $refId): ?string
    {
        $normalized = $this->normalize($refId);

        if (preg_match('/^[A-Z]+(\d+)$/', $normalized, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check if the reference ID appears to be valid format.
     *
     * @param string $refId The reference ID to check
     * @return bool True if appears to be valid format
     */
    public function isValidFormat(string $refId): bool
    {
        $normalized = $this->normalize($refId);

        // Should have alphabetic prefix followed by digits
        return (bool) preg_match('/^[A-Z]+\d+$/', $normalized);
    }

    /**
     * Calculate similarity score between two reference IDs using Levenshtein distance.
     *
     * @param string $refId1 First reference ID
     * @param string $refId2 Second reference ID
     * @return float Similarity score between 0 and 1
     */
    public function calculateSimilarity(string $refId1, string $refId2): float
    {
        $normalized1 = $this->normalize($refId1);
        $normalized2 = $this->normalize($refId2);

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

    /**
     * Compare two reference IDs and determine if they likely refer to the same record.
     *
     * Returns detailed comparison results.
     *
     * @param string $refId1 First reference ID
     * @param string $refId2 Second reference ID
     * @return array Comparison results with score and method
     */
    public function compare(string $refId1, string $refId2): array
    {
        $normalized1 = $this->normalize($refId1);
        $normalized2 = $this->normalize($refId2);

        // Exact normalized match
        if ($normalized1 === $normalized2) {
            return [
                'score' => 1.0,
                'method' => 'normalized_exact',
                'normalized_1' => $normalized1,
                'normalized_2' => $normalized2,
            ];
        }

        // Check if lab codes match
        $labCode1 = $this->extractLabCode($refId1);
        $labCode2 = $this->extractLabCode($refId2);

        if ($labCode1 !== $labCode2) {
            // Different lab codes - likely not the same record
            return [
                'score' => 0.0,
                'method' => 'different_lab_codes',
                'lab_code_1' => $labCode1,
                'lab_code_2' => $labCode2,
            ];
        }

        // Same lab code - compare numeric parts
        $number1 = $this->extractNumber($refId1);
        $number2 = $this->extractNumber($refId2);

        if ($number1 && $number2) {
            $distance = levenshtein($number1, $number2);
            $maxLen = max(strlen($number1), strlen($number2));
            $similarity = $maxLen > 0 ? 1 - ($distance / $maxLen) : 0;

            return [
                'score' => $similarity,
                'method' => 'number_levenshtein',
                'number_1' => $number1,
                'number_2' => $number2,
                'distance' => $distance,
            ];
        }

        // Fallback to full Levenshtein
        return [
            'score' => $this->calculateSimilarity($refId1, $refId2),
            'method' => 'full_levenshtein',
        ];
    }
}

<?php

namespace App\Services;

class RefIdNormalizerService
{
    // Visual similarity substitutions
    const CHAR_MAP = [
        'O' => '0', 'o' => '0',
        'I' => '1', 'l' => '1',
        'S' => '5', 's' => '5',
    ];

    /**
     * Normalize a reference ID for comparison
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
            for ($i = 0; $i < strlen($numberPart); $i++) {
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
     * Extract lab code prefix from reference ID
     */
    public function extractLabCode(string $refId): ?string
    {
        if (preg_match('/^([A-Z]+)\d/', strtoupper($refId), $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract numeric part of reference ID
     */
    public function extractNumber(string $refId): ?string
    {
        $normalized = $this->normalize($refId);

        if (preg_match('/^[A-Z]+(\d+)$/', $normalized, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

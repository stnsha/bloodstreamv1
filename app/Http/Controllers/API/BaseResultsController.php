<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PanelTag;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class BaseResultsController extends Controller
{
    /**
     * Generate panel code from panel name
     */
    protected function generatePanelCode($panelName)
    {
        // Create a simple code by taking first letters of words and removing special characters
        $code = '';
        $words = preg_split('/[\s\-\:\~]+/', $panelName);
        foreach ($words as $word) {
            if (!empty($word) && $word !== '~') {
                $code .= strtoupper(substr(trim($word), 0, 1));
            }
        }
        // Fallback if code is too short
        if (strlen($code) < 3) {
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $panelName), 0, 3));
        }
        return $code;
    }

    /**
     * Generate test code from test name
     */
    protected function generateTestCode($testName)
    {
        // Create a simple code by taking first letters of words
        $code = '';
        $words = preg_split('/[\s\-\(\)]+/', $testName);
        foreach ($words as $word) {
            if (!empty($word) && strlen($word) > 2) { // Skip short words like "of", "in", etc.
                $code .= strtoupper(substr(trim($word), 0, 1));
            }
        }
        // Fallback if code is too short
        if (strlen($code) < 3) {
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $testName), 0, 3));
        }
        return $code;
    }

    /**
     * Generate doctor code from doctor name
     */
    protected function generateDoctorCode($doctorName)
    {
        // Extract meaningful words and create code
        $code = '';
        $words = preg_split('/[\s\-\(\)]+/', $doctorName);
        foreach ($words as $word) {
            if (!empty($word) && !in_array(strtolower($word), ['sdn', 'bhd', 'network', 'pharmacy', 'the', 'and', 'of'])) {
                $code .= strtoupper(substr(trim($word), 0, 1));
            }
        }
        // Limit to reasonable length and ensure minimum length
        if (strlen($code) > 6) {
            $code = substr($code, 0, 6);
        } elseif (strlen($code) < 3) {
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $doctorName), 0, 6));
        }
        return $code;
    }

    /**
     * Convert datetime string to Carbon instance
     * Handles formats: YYYYMMDD and YYYYMMDDHHMM
     */
    protected function convertDatetime($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            // Handle YYYYMMDD format (8 digits)
            if (strlen($dateString) === 8) {
                return Carbon::createFromFormat('Ymd H:i:s', $dateString . ' 00:00:00');
            }
            // Handle YYYYMMDDHHMM format (12 digits)
            elseif (strlen($dateString) === 12) {
                return Carbon::createFromFormat('YmdHi', $dateString);
            }
            // Handle other potential formats
            else {
                return Carbon::parse($dateString);
            }
        } catch (Exception $e) {
            Log::warning('Failed to parse datetime', [
                'dateString' => $dateString,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Check if an item is a TAG ON item
     */
    protected function isTagOnItem($panelName, $panelCode = null)
    {
        // Handle case where panelName might be an array or null
        $trimmedName = trimOrNull($panelName);
        $trimmedCode = trimOrNull($panelCode);
        if (!$trimmedName) {
            return false;
        }

        if ($trimmedCode) {
            $isPanelTag = PanelTag::where('code', $trimmedCode)
                ->where('name', $trimmedName)
                ->exists();

            if ($isPanelTag) {
                return true;
            }
        }

        $tagOnKeywords = ['TAG ON', 'TAGON', 'TAG-ON'];
        foreach ($tagOnKeywords as $keyword) {
            if (Str::contains(strtoupper($trimmedName), $keyword)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extract base panel name by removing TAG ON related keywords
     */
    protected function extractBasePanelName($panelName)
    {
        // Handle case where panelName might be an array or null
        $trimmed = trimOrNull($panelName);
        if (!$trimmed) {
            return '';
        }

        // Remove TAG ON related keywords and clean up
        $baseName = preg_replace('/\s*\(?\s*(TAG[\s\-]?ON)\s*\)?/i', '', $trimmed);
        $baseName = preg_replace('/\s*TAGON\s*/i', '', $baseName);

        return trimOrNull($baseName) ?: '';
    }
}
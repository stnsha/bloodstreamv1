<?php

use App\Models\Patient;
use Carbon\Carbon;

if (!function_exists('generate_lab_code')) {
    function generate_lab_code($labName)
    {
        return strtoupper(substr($labName, 0, 3));
    }
}

if (!function_exists('generate_lab_path')) {
    function generate_lab_path($labName)
    {
        $firstWord = strtok($labName, ' ');
        return ucfirst(strtolower($firstWord));
    }
}

if (!function_exists('get_email_abbrv')) {
    function get_email_abbrv($email)
    {
        $localPart = strstr($email, '@', true);
        return strtoupper(substr($localPart, 0, 3));
    }
}
if (!function_exists('checkIcno')) {
    function checkIcno($icno, $dob = null): array
    {
        $type = Patient::IC_TYPE_OTHERS;
        $gender = null;
        $age = null;

        if (strlen($icno) === 12) {
            $year = (int) substr($icno, 0, 2);
            $month = (int) substr($icno, 2, 2);
            $day = (int) substr($icno, 4, 2);
            $lastDigit = (int) substr($icno, -1);

            $currentYear = (int) date('Y');
            $fullYear = $year > ($currentYear % 100) ? 1900 + $year : 2000 + $year;

            if (checkdate($month, $day, $fullYear)) {
                $type = Patient::IC_TYPE_NRIC;
                $gender = $lastDigit % 2 === 0 ? Patient::GENDER_FEMALE : Patient::GENDER_MALE;
                $age = $currentYear - $fullYear;
            }
        }

        // Fallback: calculate age from DOB if age is still null
        if ($age === null && filled($dob)) {
            try {
                $age = Carbon::parse($dob)->age;
            } catch (\Exception $e) {
                $age = null;
            }
        }

        return [
            'icno' => $icno,
            'type' => $type,
            'gender' => $gender,
            'age' => $age,
        ];
    }
}

if (!function_exists('convertToDateTimeString')) {
    function convertToDateTimeString($date)
    {
        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}

if (!function_exists('trimOrNull')) {
    function trimOrNull($value)
    {
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}

if (!function_exists('fix_encoding')) {
    function fix_encoding($value)
    {
        return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
    }
}

if (!function_exists('sanitizeDate')) {
    function sanitizeDate($date)
    {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return null;
        }
        return $date;
    }
}

if (!function_exists('cleanJsonString')) {
    function cleanJsonString($value)
    {
        if (!$value) {
            return null;
        }
        return str_replace('\/', '/', $value);
    }
}

//Based of collected_date
if (!function_exists('calculatePatientAge')) {
    function calculatePatientAge($dob, $reportDate)
    {
        // Normalize DOB format
        if (preg_match('/^\d{8}$/', $dob)) {
            // YYYYMMDD → YYYY-MM-DD
            $dob = substr($dob, 0, 4) . '-' . substr($dob, 4, 2) . '-' . substr($dob, 6, 2);
        }

        $dobDate = new DateTime($dob);
        $reportDate = new DateTime($reportDate);

        // Get age based on report date
        return $dobDate->diff($reportDate)->y;
    }
}

if (!function_exists('extractUpperLimit')) {
    /**
     * Extract upper limit from reference range string
     * Examples:
     * "< 41" -> 41
     * "12-36" -> 36
     * "40" -> 40
     */
    function extractUpperLimit(?string $rangeValue): ?float
    {
        if (is_null($rangeValue)) {
            return null;
        }

        $range = trim($rangeValue);

        // Case: "< 41" -> extract 41
        if (str_starts_with($range, '<')) {
            return (float) trim(str_replace('<', '', $range));
        }

        // Case: "12-36" -> extract 36 (highest)
        if (str_contains($range, '-')) {
            $parts = explode('-', $range);
            return (float) trim(end($parts));
        }

        // Case: single numeric value
        if (is_numeric($range)) {
            return (float) $range;
        }

        return null;
    }
}

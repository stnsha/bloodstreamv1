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
    function checkIcno($icno): array
    {
        $type = Patient::IC_TYPE_OTHERS;
        $gender = null;
        $age = null;
        // $nationality = Patient::non;

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
                // $nationality = Patient::my;
            }
        }

        return [
            'icno' => $icno,
            'type' => $type,
            'gender' => $gender,
            'age' => $age,
            // 'nationality' => $nationality
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

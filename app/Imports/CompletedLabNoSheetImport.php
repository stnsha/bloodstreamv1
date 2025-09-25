<?php

namespace App\Imports;

use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Exception;

class CompletedLabNoSheetImport implements ToArray, WithHeadingRow
{
    protected $parentImport;
    protected static $sheetCount = 0;

    public function __construct(CompletedLabNoImport $parentImport)
    {
        $this->parentImport = $parentImport;
    }

    public function array(array $array)
    {
        try {
            self::$sheetCount++;
            $sheetName = "Sheet " . self::$sheetCount;

            Log::info("Processing {$sheetName}", [
                'total_rows' => count($array)
            ]);

            // Debug: Log first row headers to understand sheet structure
            if (!empty($array)) {
                $firstRow = array_keys($array[0]);
                Log::info("Sheet {$sheetName} headers", [
                    'headers' => $firstRow
                ]);
            }

            $processedData = [];
            $sheetStats = [
                'name' => $sheetName,
                'total_rows' => count($array),
                'processed_rows' => 0,
                'skipped_rows' => 0,
                'empty_rows' => 0,
            ];

            foreach ($array as $rowIndex => $row) {
                // Skip completely empty rows
                if ($this->isEmptyRow($row)) {
                    $sheetStats['empty_rows']++;
                    continue;
                }

                $processedRow = $this->processRow($row);
                if ($processedRow) {
                    $processedData[] = $processedRow;
                    $sheetStats['processed_rows']++;
                } else {
                    $sheetStats['skipped_rows']++;
                }
            }

            // Add processed data to parent
            $this->parentImport->addProcessedData($processedData, $sheetName);

            // Log sheet-specific summary
            Log::info("Sheet '{$sheetName}' Summary", $sheetStats);

            // Simple approach: finalize immediately after processing any sheet
            // The finalize method now handles duplicate calls gracefully
            $this->parentImport->finalize();

        } catch (Exception $e) {
            Log::error('CompletedLabNoSheetImport failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function processRow(array $row): ?array
    {
        // Skip rows with missing essential data
        if (!$this->hasEssentialData($row)) {
            return null;
        }

        return [
            'prefix' => $this->trimOrNull($row['prefix']),
            'lab_number' => $this->trimOrNull($row['lab_number']),
        ];
    }

    /**
     * Check if row has essential data for lab number import
     */
    protected function hasEssentialData(array $row): bool
    {
        // First check if the row is completely empty
        if ($this->isEmptyRow($row)) {
            return false;
        }

        $lab_number = $this->trimOrNull($row['lab_number'] ?? null);

        // Only lab_number is required, prefix is optional
        return !empty($lab_number);
    }

    /**
     * Check if a row is completely empty
     */
    protected function isEmptyRow(array $row): bool
    {
        // Remove null values and trim strings
        $cleanedRow = array_map(function ($value) {
            if (is_string($value)) {
                $trimmed = trim($value);
                return $trimmed === '' ? null : $trimmed;
            }
            return $value;
        }, $row);

        // Remove null values
        $cleanedRow = array_filter($cleanedRow, function ($value) {
            return $value !== null && $value !== '';
        });

        // If no values remain, the row is empty
        return empty($cleanedRow);
    }

    protected function trimOrNull($value)
    {
        return is_string($value) ? (trim($value) ?: null) : $value;
    }
}
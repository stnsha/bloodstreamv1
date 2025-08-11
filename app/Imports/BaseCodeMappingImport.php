<?php

namespace App\Imports;

use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Exception;

abstract class BaseCodeMappingImport implements ToArray, WithHeadingRow, WithValidation
{
    protected $labId = 2; // Innoquest Lab ID
    protected array $importStats = [
        'total_rows' => 0,
        'empty_rows' => 0,
        'skipped_rows' => 0,
        'processed_rows' => 0,
        'created_records' => 0,
        'updated_records' => 0,
        'new_data_count' => 0,
    ];
    protected static bool $isFirstFile = true;

    public function array(array $array)
    {
        try {
            $processedData = [];

            // Initialize stats
            $this->importStats['total_rows'] = count($array);
            $this->importStats['empty_rows'] = 0;
            $this->importStats['skipped_rows'] = 0;
            $this->importStats['processed_rows'] = 0;
            $this->importStats['created_records'] = 0;
            $this->importStats['updated_records'] = 0;
            $this->importStats['new_data_count'] = 0;

            foreach ($array as $rowIndex => $row) {
                // Skip completely empty rows
                if ($this->isEmptyRow($row)) {
                    $this->importStats['empty_rows']++;
                    continue;
                }

                $processedRow = $this->processRow($row);
                if ($processedRow) {
                    $processedData[] = $processedRow;
                } else {
                    $this->importStats['skipped_rows']++;
                }
            }

            $this->importStats['processed_rows'] = count($processedData);

            if (!empty($processedData)) {
                $this->store($processedData);
            }

            // Log comprehensive statistics
            $this->logImportSummary();
        } catch (Exception $e) {
            Log::error(static::class . ' import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    abstract protected function processRow(array $row): ?array;

    abstract protected function store(array $processedData): void;

    public function rules(): array
    {
        return [];
    }

    protected function trimOrNull($value)
    {
        return is_string($value) ? (trim($value) ?: null) : $value;
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

    /**
     * Check if a row has essential data for the specific import type
     * Can be overridden by child classes for more specific empty row detection
     */
    protected function hasEssentialData(array $row): bool
    {
        return !$this->isEmptyRow($row);
    }

    /**
     * Log comprehensive import summary
     */
    protected function logImportSummary(): void
    {
        $className = class_basename(static::class);
        $sheetName = str_replace('Import', '', $className);

        $summaryData = array_merge($this->importStats, [
            'sheet_type' => $sheetName,
            'is_first_file' => static::$isFirstFile
        ]);

        Log::info("=== {$sheetName} Import Summary ===", $summaryData);

        if (static::$isFirstFile) {
            Log::info("✅ {$sheetName}: Successfully stored {$this->importStats['created_records']} records into database");
        } else {
            Log::info("📊 {$sheetName}: Added {$this->importStats['new_data_count']} new records (Total created: {$this->importStats['created_records']}, Updated: {$this->importStats['updated_records']})");
        }
    }

    /**
     * Track database operations for statistics
     */
    protected function trackDatabaseOperation(string $operation, bool $wasCreated = false): void
    {
        if ($operation === 'create' || $wasCreated) {
            $this->importStats['created_records']++;
            if (!static::$isFirstFile) {
                $this->importStats['new_data_count']++;
            }
        } elseif ($operation === 'update') {
            $this->importStats['updated_records']++;
        }
    }

    /**
     * Get import statistics
     */
    public function getImportStats(): array
    {
        return $this->importStats;
    }

    /**
     * Mark subsequent files (not first file)
     */
    public static function markAsSubsequentFile(): void
    {
        static::$isFirstFile = false;
    }

    /**
     * Reset for new import session
     */
    public static function resetFileStatus(): void
    {
        static::$isFirstFile = true;
    }
}

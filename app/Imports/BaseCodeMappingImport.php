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
        'comment_skipped_rows' => 0,
        'processed_rows' => 0,
        'created_records' => 0,
        'updated_records' => 0,
        'new_data_count' => 0,
    ];

    protected array $skippedRows = [];
    protected int $maxSkippedRowsToLog = 100; // Limit logged skipped rows for performance
    protected static bool $isFirstFile = true;

    public function array(array $array)
    {
        try {
            $processedData = [];

            // Initialize stats
            $this->importStats['total_rows'] = count($array);
            $this->importStats['empty_rows'] = 0;
            $this->importStats['skipped_rows'] = 0;
            $this->importStats['comment_skipped_rows'] = 0;
            $this->importStats['processed_rows'] = 0;
            $this->importStats['created_records'] = 0;
            $this->importStats['updated_records'] = 0;
            $this->importStats['new_data_count'] = 0;

            // Initialize skipped rows collection
            $this->skippedRows = [];

            foreach ($array as $rowIndex => $row) {
                // Skip completely empty rows
                if ($this->isEmptyRow($row)) {
                    $this->importStats['empty_rows']++;
                    $this->addSkippedRow($rowIndex + 2, $row, 'Empty row'); // +2 because Excel is 1-indexed and we have headers
                    continue;
                }

                $processedRow = $this->processRow($row);
                if ($processedRow) {
                    $processedData[] = $processedRow;
                } else {
                    $this->importStats['skipped_rows']++;
                    $skipReason = $this->getSkipReason($row);
                    $this->addSkippedRow($rowIndex + 2, $row, $skipReason); // +2 because Excel is 1-indexed and we have headers
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

        Log::info($sheetName . ' Sheet Summary', [
            'total_rows' => $this->importStats['total_rows'],
            'processed_rows' => $this->importStats['processed_rows'],
            'created_records' => $this->importStats['created_records'],
            'updated_records' => $this->importStats['updated_records'],
            'new_data_count' => $this->importStats['new_data_count'],
            'skipped_rows' => $this->importStats['skipped_rows'] + $this->importStats['empty_rows'],
            'comment_skipped_rows' => $this->importStats['comment_skipped_rows']
        ]);

        // Log skipped rows details if any
        if (!empty($this->skippedRows)) {
            $totalSkippedCount = $this->importStats['skipped_rows'] + $this->importStats['empty_rows'];
            $loggedCount = count($this->skippedRows);

            $logData = [
                'total_skipped_count' => $totalSkippedCount,
                'logged_count' => $loggedCount,
                'skipped_rows' => $this->skippedRows
            ];

            // Add note if we've reached the logging limit
            if ($totalSkippedCount > $this->maxSkippedRowsToLog) {
                $logData['note'] = "Showing first {$this->maxSkippedRowsToLog} skipped rows only. Total skipped: {$totalSkippedCount}";
            }

            // Log::info($sheetName . ' Skipped Rows Details', $logData);
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

    /**
     * Add a skipped row to the collection for logging
     */
    protected function addSkippedRow(int $excelRowNumber, array $rawRow, string $reason): void
    {
        // Limit the number of skipped rows we collect to prevent memory issues
        if (count($this->skippedRows) < $this->maxSkippedRowsToLog) {
            $this->skippedRows[] = [
                'excel_row_number' => $excelRowNumber,
                'reason' => $reason,
                'raw_data' => $this->sanitizeRowForLogging($rawRow),
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * Track comment skipped rows during store processing
     */
    protected function trackCommentSkip(array $data, string $reason = 'Contains comment in panel_name'): void
    {
        $this->importStats['comment_skipped_rows']++;
        
        // Log detailed info for comment skips (limit to prevent memory issues)
        if (count($this->skippedRows) < $this->maxSkippedRowsToLog) {
            $this->skippedRows[] = [
                'excel_row_number' => 'N/A (store phase)',
                'reason' => $reason,
                'raw_data' => [
                    'panel_code' => $data['panel_code'] ?? null,
                    'panel_name' => $data['panel_name'] ?? null,
                    'panel_item_code' => $data['panel_item_code'] ?? null,
                    'panel_item_name' => $data['panel_item_name'] ?? null
                ],
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * Get the reason why a row was skipped (to be overridden by child classes)
     */
    protected function getSkipReason(array $row): string
    {
        return 'Failed validation or processing requirements';
    }

    /**
     * Sanitize row data for logging (remove sensitive data, limit size)
     */
    protected function sanitizeRowForLogging(array $row): array
    {
        $sanitized = [];
        foreach ($row as $key => $value) {
            // Limit string length to prevent huge logs
            if (is_string($value) && strlen($value) > 200) {
                $sanitized[$key] = substr($value, 0, 200) . '...[truncated]';
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
}
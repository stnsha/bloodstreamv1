<?php

namespace App\Imports;

use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Models\TestResult;
use Exception;

class CompletedLabNoImport implements WithMultipleSheets
{
    protected $labId = 2; // Innoquest Lab ID
    protected array $allProcessedData = [];
    protected array $combinedStats = [
        'total_sheets' => 0,
        'total_rows' => 0,
        'processed_rows' => 0,
        'skipped_rows' => 0,
        'empty_rows' => 0,
    ];
    protected array $matchResults = [];

    public function sheets(): array
    {
        // Create individual sheet handlers for each sheet
        $sheetHandler = new CompletedLabNoSheetImport($this);
        
        return [
            0 => $sheetHandler,  // First sheet (1-5 Sept)
            1 => $sheetHandler,  // Second sheet (6-9 Sept)
        ];
    }

    public function addProcessedData(array $data, string $sheetName): void
    {
        $this->allProcessedData = array_merge($this->allProcessedData, $data);
        
        // Update stats
        $this->combinedStats['total_sheets']++;
        $this->combinedStats['processed_rows'] += count($data);
        
        Log::info("Sheet '{$sheetName}' processed", [
            'rows_added' => count($data),
            'total_combined_rows' => count($this->allProcessedData)
        ]);
    }

    public function finalize(): void
    {
        // Store all combined data
        if (!empty($this->allProcessedData)) {
            $this->store($this->allProcessedData);
        } else {
            Log::warning("No processed data found to store");
        }

        // Log comprehensive statistics
        $this->logCombinedSummary();
    }


    protected function store(array $processedData): void
    {
        // Get all lab_no from TestResult database
        $dbLabNumbers = TestResult::pluck('lab_no')->toArray();
        
        // Process each record from Excel
        foreach ($processedData as $record) {
            $prefix = $record['prefix'];
            $labNumber = $record['lab_number'];
            
            // Combine prefix and lab_number to match database format
            $labNo = !empty($prefix) ? $prefix . '-' . $labNumber : '25-'.$labNumber;
            
            // Check if this lab number exists in database
            $existsInDb = in_array($labNo, $dbLabNumbers);
            
            $this->matchResults[] = [
                'from_excel' => $labNo,
                'in_db' => $existsInDb ? $labNo : '',
                'is_exist' => $existsInDb
            ];
        }
        
        // Calculate summary statistics
        $totalRecords = count($processedData);
        $existCount = count(array_filter($this->matchResults, fn($result) => $result['is_exist']));
        $notExistCount = count(array_filter($this->matchResults, fn($result) => !$result['is_exist']));

        // Store summary for final output
        $this->combinedStats['summary'] = [
            'total_lab_numbers' => $totalRecords,
            'exist_in_db' => $existCount,
            'not_exist_in_db' => $notExistCount
        ];

        Log::info("Lab Number Matching Complete", [
            'total_excel_records' => $totalRecords,
            'total_matches' => $existCount,
            'total_no_matches' => $notExistCount,
        ]);
    }

    /**
     * Log comprehensive import summary
     */
    protected function logCombinedSummary(): void
    {
        Log::info('Completed Lab Number Import Summary', [
            'total_sheets_processed' => $this->combinedStats['total_sheets'],
            'combined_total_rows' => $this->combinedStats['total_rows'],
            'combined_processed_rows' => $this->combinedStats['processed_rows'],
            'combined_skipped_rows' => $this->combinedStats['skipped_rows'],
            'combined_empty_rows' => $this->combinedStats['empty_rows'],
            'final_combined_records' => count($this->allProcessedData),
        ]);
    }

    /**
     * Get combined import statistics
     */
    public function getCombinedStats(): array
    {
        return array_merge($this->combinedStats, [
            'final_combined_records' => count($this->allProcessedData)
        ]);
    }

    /**
     * Get all processed data (for external access if needed)
     */
    public function getAllProcessedData(): array
    {
        return $this->allProcessedData;
    }

    /**
     * Get match results as JSON
     */
    public function getMatchResults(): array
    {
        return $this->matchResults;
    }
}
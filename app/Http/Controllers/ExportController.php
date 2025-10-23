<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportController extends Controller
{
    public function exportBp() {
        try {
            ini_set('memory_limit', '1024M');
            ini_set('max_execution_time', 600);
            set_time_limit(600);

            Log::info("Starting BP data extraction...");

            // Step 1: Get ICs with high BP
            Log::info("Step 1: Finding patients with high BP...");

            $sql = "
                SELECT DISTINCT r.ic
                FROM check_record_details d
                JOIN check_record r ON r.id = d.record_id
                WHERE
                    d.parameter = 10
                    AND (
                        CAST(SUBSTRING_INDEX(d.value, '/', 1) AS UNSIGNED) >= 140
                        OR
                        CAST(SUBSTRING_INDEX(d.value, '/', -1) AS UNSIGNED) >= 90
                    )
                    AND YEAR(r.date_time) IN (2024, 2025)
            ";

            $icNumbers = DB::connection('mysql2')
                ->select($sql);

            $icNumbers = array_column($icNumbers, 'ic');

            Log::info("Found " . count($icNumbers) . " ICs");

            if (empty($icNumbers)) {
                return response()->json(['error' => 'No patients found.'], 404);
            }

            // Step 2: Get BP and HR data
            Log::info("Step 2: Retrieving BP and HR data...");

            $chunkSize = 1000;
            $data = [];

            foreach (array_chunk($icNumbers, $chunkSize) as $chunk) {
                $icList = "'" . implode("','", array_map(function($ic) {
                    return DB::connection('mysql2')->getPdo()->quote($ic);
                }, $chunk)) . "'";

                // Remove extra quotes from PDO quote
                $icList = str_replace("''", "'", $icList);
                $icList = trim($icList, "'");

                $sql = "
                    SELECT r.ic, d.parameter, d.value, r.date_time
                    FROM check_record_details d
                    JOIN check_record r ON r.id = d.record_id
                    WHERE r.ic IN ($icList)
                    AND d.parameter IN (10, 11)
                    AND YEAR(r.date_time) IN (2024, 2025)
                    ORDER BY r.ic, d.parameter, r.date_time DESC
                ";

                $results = DB::connection('mysql2')->select($sql);

                $tempData = [];
                foreach ($results as $row) {
                    $ic = $row->ic;
                    $param = $row->parameter;

                    if (!isset($tempData[$ic][$param])) {
                        if (!isset($data[$ic])) {
                            $data[$ic] = [
                                'icno' => $ic,
                                'blood_pressure' => null,
                                'heart_rate' => null,
                                'glucose' => null,
                                'hba1c' => null,
                                'creatinine' => null,
                                'egfr' => null,
                                'urine_protein' => null
                            ];
                        }

                        if ($param == '10') {
                            $data[$ic]['blood_pressure'] = $row->value;
                        } elseif ($param == '11') {
                            $data[$ic]['heart_rate'] = $row->value;
                        }

                        $tempData[$ic][$param] = true;
                    }
                }

                unset($tempData);
            }

            Log::info("BP/HR data: " . count($data) . " records");

            // Step 3: Process lab results
            if (!empty($data)) {
                Log::info("Step 3: Processing lab results...");

                $icNumbers = array_keys($data);

                $fieldMap = [
                    'GLUCOSESERUM/PLASMA' => 'glucose',
                    'Glucose (serum/plasma)' => 'glucose',
                    'HBA1C IFCC' => 'hba1c',
                    'CREATININE SERUM' => 'creatinine',
                    'Creatinine' => 'creatinine',
                    'EGFR' => 'egfr',
                    'eGFR' => 'egfr',
                    'PROTEIN URINE MICRO' => 'urine_protein',
                    'Protein' => 'urine_protein'
                ];

                // Process bsConn (mysql - bsv1_production)
                $bsQuery = "
                    SELECT
                        p.icno,
                        pni.name as panel_item_name,
                        i.value,
                        r.created_at
                    FROM test_result_items i
                    JOIN test_results r ON r.id = i.test_result_id
                    JOIN patients p ON p.id = r.patient_id
                    JOIN panel_panel_items pi ON pi.id = i.panel_panel_item_id
                    JOIN panel_items pni ON pni.id = pi.panel_item_id
                    WHERE p.icno IN ({{IC_LIST}})
                    AND i.panel_panel_item_id IN (50, 51, 218, 42, 219, 43, 14, 294)
                    ORDER BY p.icno, pni.name, r.created_at DESC
                ";

                $labChunkSize = 500;

                foreach (array_chunk($icNumbers, $labChunkSize) as $chunk) {
                    $this->processLabResults('mysql', $data, $chunk, $fieldMap, $bsQuery, 'icno', 'panel_item_name', 'value');
                }

                Log::info("bsConn complete");

                // Process btConn (mysql3 - blood_test_v1)
                $btQuery = "
                    SELECT
                        r.icno,
                        p.name as panel_item_name,
                        i.result_value as value,
                        r.created_at
                    FROM test_result_items i
                    JOIN panel_items p ON p.id = i.panel_item_id
                    JOIN test_results r ON r.id = i.test_result_id
                    WHERE r.icno IN ({{IC_LIST}})
                    AND i.panel_item_id IN (1, 164, 374, 112, 424, 52, 228, 407, 73, 291, 68, 180, 185, 188, 251, 266, 268, 271, 286, 319, 352, 411, 423, 78)
                    ORDER BY r.icno, p.name, r.created_at DESC
                ";

                foreach (array_chunk($icNumbers, $labChunkSize) as $chunk) {
                    $this->processLabResults('mysql3', $data, $chunk, $fieldMap, $btQuery, 'icno', 'panel_item_name', 'value');
                }

                Log::info("btConn complete");
            }

            // Filter only records where ALL fields are NOT NULL
            Log::info("Filtering complete records...");
            $completeData = array_filter($data, function($record) {
                return $record['blood_pressure'] !== null
                    && $record['heart_rate'] !== null
                    && $record['glucose'] !== null
                    && $record['hba1c'] !== null
                    && $record['creatinine'] !== null
                    && $record['egfr'] !== null
                    && $record['urine_protein'] !== null;
            });

            // Limit to 5000 records
            $completeData = array_slice($completeData, 0, 5000);
            $completeData = array_values($completeData);

            Log::info("Complete records (max 5000): " . count($completeData));

            if (empty($completeData)) {
                return response()->json(['error' => 'No complete records found.'], 404);
            }

            // Create Excel file
            Log::info("Creating Excel file...");

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ['IC Number', 'Blood Pressure', 'Heart Rate', 'Glucose', 'HbA1c', 'Creatinine', 'eGFR', 'Urine Protein'];
            $sheet->fromArray($headers, null, 'A1');

            $row = 2;
            foreach ($completeData as $record) {
                $sheet->fromArray([
                    $record['icno'],
                    $record['blood_pressure'] ?? '',
                    $record['heart_rate'] ?? '',
                    $record['glucose'] ?? '',
                    $record['hba1c'] ?? '',
                    $record['creatinine'] ?? '',
                    $record['egfr'] ?? '',
                    $record['urine_protein'] ?? ''
                ], null, 'A' . $row);
                $row++;
            }

            // Save to storage/app/public/excel
            $directory = storage_path('app/public/excel');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $filename = "high_bp_patients_" . date('Y-m-d_His') . ".xlsx";
            $filepath = $directory . '/' . $filename;

            $writer = new Xlsx($spreadsheet);
            $writer->save($filepath);

            Log::info("Excel file created: " . $filename);

            return response()->json([
                'success' => true,
                'message' => 'Excel file created successfully!',
                'filename' => $filename,
                'total_records' => count($completeData),
                'file_path' => 'storage/excel/' . $filename,
                'download_url' => url('storage/excel/' . $filename)
            ]);

        } catch (\Exception $e) {
            Log::error("Error in exportBp: " . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'error' => 'Failed to export data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function processLabResults($connection, &$data, $icChunk, $fieldMap, $query, $icColumn = 'icno', $nameColumn = 'panel_item_name', $valueColumn = 'value')
    {
        $escaped = array_map(function($ic) use ($connection) {
            return DB::connection($connection)->getPdo()->quote($ic);
        }, $icChunk);

        // Remove extra quotes from PDO quote
        $escaped = array_map(function($quoted) {
            return trim($quoted, "'");
        }, $escaped);

        $icList = "'" . implode("','", $escaped) . "'";

        $sql = str_replace('{{IC_LIST}}', $icList, $query);
        $results = DB::connection($connection)->select($sql);

        if (empty($results)) {
            return;
        }

        $seenParams = [];

        foreach ($results as $row) {
            $ic = $row->{$icColumn};
            $paramName = $row->{$nameColumn};

            if (!isset($seenParams[$ic][$paramName])) {
                if (isset($data[$ic]) && isset($fieldMap[$paramName])) {
                    $field = $fieldMap[$paramName];

                    if ($data[$ic][$field] === null) {
                        $data[$ic][$field] = $row->{$valueColumn};
                    }
                }

                $seenParams[$ic][$paramName] = true;
            }
        }

        unset($seenParams);
    }
}
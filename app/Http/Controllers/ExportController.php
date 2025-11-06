<?php

namespace App\Http\Controllers;

use App\Jobs\ExportBpJob;
use App\Models\Patient;
use App\Models\TestResult;
use App\Traits\Octopus;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportController extends Controller
{
    use Octopus;
    public function exportBp() {
        try {
            Log::channel('bp-log')->info("BP Export job dispatched");

            // Dispatch job to background queue
            ExportBpJob::dispatch();

            return response()->json([
                'success' => true,
                'message' => 'BP export job has been queued. The file will be generated in the background. Check storage/excel folder or logs for progress.',
            ]);

        } catch (\Exception $e) {
            Log::channel('bp-log')->error("Error dispatching exportBp job: " . $e->getMessage());

            return response()->json([
                'error' => 'Failed to dispatch export job',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function exportAge()
    {
        try {
            Log::info("exportAge: Starting...");

            // Get all patients age 40 and above with their IC numbers
            $patients = Patient::where('age', '>=', 40)->get();
            $icNumbers = $patients->pluck('icno')->toArray();

            Log::info("exportAge: Found " . count($icNumbers) . " patients age 40+");

            if (empty($icNumbers)) {
                return response()->json([
                    'success' => true,
                    'total_records' => 0,
                    'data' => []
                ]);
            }

            // Query panel items for these patients
            $chunkSize = 500;
            $results = [];

            foreach (array_chunk($icNumbers, $chunkSize) as $chunk) {
                $escaped = array_map(function ($ic) {
                    return DB::getPdo()->quote($ic);
                }, $chunk);

                // Remove outer quotes from PDO quote
                $escaped = array_map(function ($quoted) {
                    return trim($quoted, "'");
                }, $escaped);

                $icList = "'" . implode("','", $escaped) . "'";

                $sql = "
                    SELECT
                        p.icno,
                        p.name as patient_name,
                        pni.name as panel_item_name,
                        i.value,
                        r.collected_date
                    FROM test_result_items i
                    JOIN test_results r ON r.id = i.test_result_id
                    JOIN patients p ON p.id = r.patient_id
                    JOIN panel_panel_items pi ON pi.id = i.panel_panel_item_id
                    JOIN panel_items pni ON pni.id = pi.panel_item_id
                    WHERE p.icno IN ($icList)
                    AND i.panel_panel_item_id IN (50, 51, 218, 42, 219, 43, 14, 294)
                    ORDER BY p.icno, pni.name, r.collected_date DESC
                ";

                $chunkResults = DB::select($sql);

                // Group results by icno and map panel items to fields
                foreach ($chunkResults as $row) {
                    $icno = $row->icno;

                    if (!isset($results[$icno])) {
                        $results[$icno] = [
                            'icno' => $row->icno,
                            'patient_name' => $row->patient_name,
                            'collected_date' => $row->collected_date,
                            'creatinine' => null,
                            'egfr' => null,
                            'glucose' => null,
                            'hba1c' => null,
                            'protein' => null
                        ];
                    }

                    // Map panel item names to fields
                    $panelName = strtolower($row->panel_item_name);
                    if (strpos($panelName, 'creatinine') !== false) {
                        $results[$icno]['creatinine'] = $row->value;
                    } elseif (strpos($panelName, 'egfr') !== false) {
                        $results[$icno]['egfr'] = $row->value;
                    } elseif (strpos($panelName, 'glucose') !== false) {
                        $results[$icno]['glucose'] = $row->value;
                    } elseif (strpos($panelName, 'hba1c') !== false) {
                        $results[$icno]['hba1c'] = $row->value;
                    } elseif (strpos($panelName, 'protein') !== false) {
                        $results[$icno]['protein'] = $row->value;
                    }
                }
            }

            // Filter only patients with ALL fields (no nulls)
            $completeResults = array_filter($results, function ($record) {
                return $record['creatinine'] !== null
                    && $record['egfr'] !== null
                    && $record['glucose'] !== null
                    && $record['hba1c'] !== null
                    && $record['protein'] !== null;
            });

            // Convert to array values
            $completeResults = array_values($completeResults);

            Log::info("exportAge: Retrieved " . count($completeResults) . " patients with ALL panel items");

            if (empty($completeResults)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No patients found with all required panel items',
                    'total_records' => 0
                ], 404);
            }

            // Create Excel file
            Log::info("exportAge: Creating Excel file...");

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $headers = ['Name', 'IC No', 'Collected Date', 'Creatinine', 'eGFR', 'Glucose', 'HbA1c', 'Protein'];
            $sheet->fromArray($headers, null, 'A1');

            // Add data rows
            $row = 2;
            foreach ($completeResults as $record) {
                $sheet->fromArray([
                    $record['patient_name'],
                    $record['icno'],
                    $record['collected_date'],
                    $record['creatinine'],
                    $record['egfr'],
                    $record['glucose'],
                    $record['hba1c'],
                    $record['protein']
                ], null, 'A' . $row);
                $row++;
            }

            // Save to storage/app/public/excel
            $directory = storage_path('app/public/excel');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $filename = "bsv1_patients_" . date('Y-m-d_His') . ".xlsx";
            $filepath = $directory . '/' . $filename;

            $writer = new Xlsx($spreadsheet);
            $writer->save($filepath);

            Log::info("exportAge: Excel file created: " . $filename);

            return response()->json([
                'success' => true,
                'total_patients' => count($icNumbers),
                'total_complete_records' => count($completeResults),
                'filename' => $filename,
                'file_path' => 'storage/excel/' . $filename,
                'download_url' => url('storage/excel/' . $filename)
            ]);
        } catch (Exception $e) {
            Log::error("Error in exportAge: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());

            return response()->json([
                'error' => 'Failed to export data',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function exportBtAge()
    {
        try {
            // Increase memory and time limits
            ini_set('memory_limit', '2048M');
            ini_set('max_execution_time', 1800); // 30 minutes
            set_time_limit(1800);

            Log::info("exportBtAge: Starting...");

            // Get all unique IC numbers from blood_test_v1 database
            $allIcnos = DB::connection('mysql3')
                ->table('test_results')
                ->distinct()
                ->pluck('icno')
                ->toArray();

            Log::info("exportBtAge: Found " . count($allIcnos) . " unique ICs");

            // Filter IC numbers: length 12 and age 40+
            $filteredIcnos = [];
            foreach ($allIcnos as $icno) {
                if (strlen($icno) === 12) {
                    $icData = checkIcno($icno);
                    if ($icData['age'] !== null && $icData['age'] >= 40) {
                        $filteredIcnos[] = $icno;
                    }
                }
            }

            // Free memory
            unset($allIcnos);

            Log::info("exportBtAge: Found " . count($filteredIcnos) . " patients age 40+");

            if (empty($filteredIcnos)) {
                return response()->json([
                    'success' => true,
                    'total_records' => 0,
                    'data' => []
                ]);
            }

            // Query panel items for these patients
            $chunkSize = 1000; // Increase chunk size for better performance
            $results = [];
            $chunkCount = 0;
            $totalChunks = ceil(count($filteredIcnos) / $chunkSize);

            foreach (array_chunk($filteredIcnos, $chunkSize) as $chunk) {
                $chunkCount++;
                Log::info("exportBtAge: Processing chunk {$chunkCount}/{$totalChunks}");
                $escaped = array_map(function ($ic) {
                    return DB::connection('mysql3')->getPdo()->quote($ic);
                }, $chunk);

                // Remove outer quotes from PDO quote
                $escaped = array_map(function ($quoted) {
                    return trim($quoted, "'");
                }, $escaped);

                $icList = "'" . implode("','", $escaped) . "'";

                $sql = "
                    SELECT
                        r.icno,
                        p.name as panel_item_name,
                        i.result_value as value,
                        r.collected_date
                    FROM test_result_items i
                    JOIN panel_items p ON p.id = i.panel_item_id
                    JOIN test_results r ON r.id = i.test_result_id
                    WHERE r.icno IN ($icList)
                    AND i.panel_item_id IN (1, 164, 374, 112, 424, 52, 228, 407, 73, 291, 68, 180, 185, 188, 251, 266, 268, 271, 286, 319, 352, 411, 423, 78)
                    ORDER BY r.icno, p.name, r.collected_date DESC
                ";

                $chunkResults = DB::connection('mysql3')->select($sql);

                // Group results by icno and map panel items to fields
                foreach ($chunkResults as $row) {
                    $icno = $row->icno;

                    if (!isset($results[$icno])) {
                        $icData = checkIcno($icno);
                        $results[$icno] = [
                            'icno' => $row->icno,
                            'patient_name' => null, // blood_test_v1 doesn't have patient name in test_results
                            'collected_date' => $row->collected_date,
                            'age' => $icData['age'],
                            'creatinine' => null,
                            'egfr' => null,
                            'glucose' => null,
                            'hba1c' => null,
                            'protein' => null
                        ];
                    }

                    // Map panel item names to fields
                    $panelName = strtolower($row->panel_item_name);
                    if (strpos($panelName, 'creatinine') !== false) {
                        $results[$icno]['creatinine'] = $row->value;
                    } elseif (strpos($panelName, 'egfr') !== false) {
                        $results[$icno]['egfr'] = $row->value;
                    } elseif (strpos($panelName, 'glucose') !== false) {
                        $results[$icno]['glucose'] = $row->value;
                    } elseif (strpos($panelName, 'hba1c') !== false) {
                        $results[$icno]['hba1c'] = $row->value;
                    } elseif (strpos($panelName, 'protein') !== false) {
                        $results[$icno]['protein'] = $row->value;
                    }
                }
            }

            // Filter only patients with ALL fields (no nulls)
            $completeResults = array_filter($results, function ($record) {
                return $record['creatinine'] !== null
                    && $record['egfr'] !== null
                    && $record['glucose'] !== null
                    && $record['hba1c'] !== null
                    && $record['protein'] !== null;
            });

            // Convert to array values
            $completeResults = array_values($completeResults);

            Log::info("exportBtAge: Retrieved " . count($completeResults) . " patients with ALL panel items");

            if (empty($completeResults)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No patients found with all required panel items',
                    'total_records' => 0
                ], 404);
            }

            // Fetch patient names from API in batches
            Log::info("exportBtAge: Fetching patient names from API...");
            $completeIcnos = array_column($completeResults, 'icno');
            $patientNames = $this->fetchPatientNamesBatch($completeIcnos);

            // Update results with patient names
            foreach ($completeResults as &$record) {
                $record['patient_name'] = $patientNames[$record['icno']] ?? null;
            }
            unset($record);

            Log::info("exportBtAge: Patient names fetched");

            // Create Excel file
            Log::info("exportBtAge: Creating Excel file...");

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $headers = ['Name', 'IC No', 'Collected Date', 'Creatinine', 'eGFR', 'Glucose', 'HbA1c', 'Protein'];
            $sheet->fromArray($headers, null, 'A1');

            // Add data rows
            $row = 2;
            foreach ($completeResults as $record) {
                $sheet->fromArray([
                    $record['patient_name'] ?? '', // Will be empty as blood_test_v1 doesn't have name
                    $record['icno'],
                    $record['collected_date'],
                    $record['creatinine'],
                    $record['egfr'],
                    $record['glucose'],
                    $record['hba1c'],
                    $record['protein']
                ], null, 'A' . $row);
                $row++;
            }

            // Save to storage/app/public/excel
            $directory = storage_path('app/public/excel');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $filename = "bsv1_bt_patients_" . date('Y-m-d_His') . ".xlsx";
            $filepath = $directory . '/' . $filename;

            $writer = new Xlsx($spreadsheet);
            $writer->save($filepath);

            Log::info("exportBtAge: Excel file created: " . $filename);

            return response()->json([
                'success' => true,
                'total_patients' => count($filteredIcnos),
                'total_complete_records' => count($completeResults),
                'filename' => $filename,
                'file_path' => 'storage/excel/' . $filename,
                'download_url' => url('storage/excel/' . $filename)
            ]);
        } catch (Exception $e) {
            Log::error("Error in exportBtAge: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());

            return response()->json([
                'error' => 'Failed to export data',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    private function fetchPatientNamesBatch($icnos)
    {
        $patientNames = [];
        $batchSize = 25;
        $batches = array_chunk($icnos, $batchSize);
        $totalBatches = count($batches);

        Log::info("fetchPatientNamesBatch: Processing " . count($icnos) . " ICs in {$totalBatches} batches");

        foreach ($batches as $index => $batch) {
            $batchNum = $index + 1;
            Log::info("fetchPatientNamesBatch: Processing batch {$batchNum}/{$totalBatches}");

            try {
                // Send all 25 ICs at once
                $data = [
                    'username' => config('credentials.odb_api.username'),
                    'password' => config('credentials.odb_api.password'),
                    'ics' => array_values($batch) // Array of ICs
                ];

                $response = $this->callAPI('POST', "http://octopusdb.info:8080/api/customerByBatchIC.php", json_encode($data), '');
                $result = json_decode($response, true);

                // Process response - API returns array of [{ic, name}, ...]
                if (is_array($result)) {
                    foreach ($result as $customer) {
                        if (isset($customer['ic']) && isset($customer['name'])) {
                            $patientNames[$customer['ic']] = $customer['name'];
                        }
                    }
                }

                // Set null for ICs not found in API response
                foreach ($batch as $icno) {
                    if (!isset($patientNames[$icno])) {
                        $patientNames[$icno] = null;
                    }
                }
            } catch (Exception $e) {
                Log::error("fetchPatientNamesBatch: Error fetching batch {$batchNum}: " . $e->getMessage());

                // Set null for all ICs in failed batch
                foreach ($batch as $icno) {
                    $patientNames[$icno] = null;
                }
            }

            // Delay between batches (500ms instead of 1 second for speed)
            if ($batchNum < $totalBatches) {
                usleep(500000); // 0.5 seconds
            }
        }

        return $patientNames;
    }
}
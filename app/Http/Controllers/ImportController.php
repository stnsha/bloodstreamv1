<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Imports\BaseCodeMappingImport;
use App\Imports\CodeMappingImport;
use App\Models\Panel;
use App\Models\PanelBridge;
use App\Models\PanelItem;
use App\Models\PanelPanelItem;
use App\Models\Patient;
use App\Models\ReferenceRange;
use App\Models\TestResult;
use App\Models\TestResultItem;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class ImportController extends Controller
{
    protected $labId = 2;

    public static function innoquestCodeMapping()
    {
        $processedFiles = 0;
        $totalFiles = 0;
        $errors = [];

        try {
            // Get all Excel files from the public/files directory
            $directory = public_path('files');
            $files = File::files($directory);

            // Filter for Excel files only, exclude temporary files
            $excelFiles = array_filter($files, function ($file) {
                return in_array($file->getExtension(), ['xlsx', 'xls', 'csv']);
            });

            // Sort files by modification time (oldest first)
            usort($excelFiles, function ($a, $b) {
                return $a->getMTime() <=> $b->getMTime();
            });

            $totalFiles = count($excelFiles);

            if (empty($excelFiles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Excel files found in public/files directory'
                ], 404);
            }

            Log::info("Starting Innoquest code mapping import for {$totalFiles} files (oldest first)");

            // Reset file status for new import session
            BaseCodeMappingImport::resetFileStatus();

            // Process each Excel file (sorted oldest first)
            foreach ($excelFiles as $fileIndex => $file) {
                $filename = $file->getFilename();
                $fileDate = date('Y-m-d H:i:s', $file->getMTime());

                try {
                    Log::info("📁 Processing file " . ($fileIndex + 1) . "/{$totalFiles}: {$filename} (Modified: {$fileDate})");

                    // Use the new CodeMappingImport with dynamic sheet detection
                    $codeMappingImport = new CodeMappingImport();
                    $codeMappingImport->import($file->getPathname());

                    $processedFiles++;

                    // Log file completion summary
                    Log::info("✅ File " . ($fileIndex + 1) . "/{$totalFiles} completed: {$filename}");
                    Log::info("=================== FILE IMPORT SUMMARY ===================");

                    // Mark subsequent files (not first file anymore)
                    if ($fileIndex === 0) {
                        BaseCodeMappingImport::markAsSubsequentFile();
                    }
                } catch (Exception $e) {
                    $error = [
                        'file' => $filename,
                        'error' => $e->getMessage(),
                        'line' => $e->getLine(),
                        'file_path' => $e->getFile(),
                        'file_date' => $fileDate
                    ];

                    $errors[] = $error;

                    Log::error("❌ Failed to import file " . ($fileIndex + 1) . "/{$totalFiles}: {$filename}", [
                        'file_date' => $fileDate,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    // Continue processing other files instead of stopping
                    continue;
                }
            }

            // Prepare response based on results
            $message = "Processed {$processedFiles} out of {$totalFiles} Excel files";

            if (!empty($errors)) {
                $message .= " with " . count($errors) . " errors";
            }

            Log::info("Innoquest code mapping import completed", [
                'total_files' => $totalFiles,
                'processed_files' => $processedFiles,
                'errors' => count($errors)
            ]);

            return response()->json([
                'success' => $processedFiles > 0,
                'message' => $message,
                'statistics' => [
                    'total_files' => $totalFiles,
                    'processed_files' => $processedFiles,
                    'failed_files' => count($errors),
                ],
                'errors' => $errors
            ], $processedFiles > 0 ? 200 : 500);
        } catch (Exception $e) {
            Log::error('Critical error in innoquestCodeMapping import', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Critical error processing Excel files: ' . $e->getMessage(),
                'statistics' => [
                    'total_files' => $totalFiles,
                    'processed_files' => $processedFiles,
                    'failed_files' => count($errors),
                ]
            ], 500);
        }
    }

    public function panels()
    {
        try {
            $baseUrl = rtrim(env('MYHEALTH_API_URL'), '/');
            $token = env('BLOOD_STREAM_V1_TOKEN');

            if (!$baseUrl || !$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'API URL or token not configured'
                ], 500);
            }

            Log::info('Starting panels import from: ' . $baseUrl);

            // Step 1: Get panels
            $panelResponse = Http::withHeaders([
                'Authorization' => $token
            ])
                ->timeout(30)
                ->get($baseUrl . '/api/panels');


            if (!$panelResponse->successful()) {
                throw new Exception('Failed to fetch panels: ' . $panelResponse->body());
            }

            $panels = $panelResponse['data'];

            $processedPanels = 0;
            $processedItems = 0;
            $createdBridges = 0;

            foreach ($panels as $panel) {
                try {
                    $old_panel_id = $panel['id'];
                    $existingPanel = Panel::with('panelItems')->where('code', $panel['code'])->first();

                    if (!$existingPanel) {
                        Log::warning('Panel not found in current system', [
                            'old_panel_id' => $old_panel_id,
                            'code' => $panel['code'],
                            'name' => $panel['name'],
                        ]);
                        continue;
                    }

                    $panel_id = $existingPanel->id;
                    Log::info("Processing panel: {$panel['name']} (Code: {$panel['code']})");

                    // Process each panel item from the old system
                    foreach ($panel['panel_items'] as $oldItem) {
                        try {
                            $old_panel_item_id = $oldItem['id'];
                            $matchFound = false;

                            // Try to match with existing panel items
                            foreach ($existingPanel->panelItems as $currentPanelItem) {
                                $normalized1 = preg_replace('/[[:punct:]\s]+/', '', strtolower($currentPanelItem->name));
                                $normalized2 = preg_replace('/[[:punct:]\s]+/', '', strtolower($oldItem['name']));

                                if (strcasecmp($normalized1, $normalized2) === 0) {
                                    $matchFound = true;

                                    // Get the panel_panel_item relationship
                                    $panelPanelItem = PanelPanelItem::where('panel_id', $panel_id)
                                        ->where('panel_item_id', $currentPanelItem->id)
                                        ->first();

                                    if ($panelPanelItem) {
                                        // Create bridge mapping
                                        $bridge = PanelBridge::firstOrCreate([
                                            'panel_panel_item_id' => $panelPanelItem->id,
                                            'old_panel_id' => $old_panel_id,
                                            'old_panel_item_id' => $old_panel_item_id,
                                        ]);

                                        if ($bridge->wasRecentlyCreated) {
                                            $createdBridges++;
                                            Log::info("Bridge created for matched item", [
                                                'bridge_id' => $bridge->id,
                                                'old_panel_item' => $oldItem['name'],
                                                'current_panel_item' => $currentPanelItem->name
                                            ]);
                                        }

                                        // Process reference ranges
                                        foreach ($oldItem['reference_ranges'] as $refRange) {
                                            if (filled($refRange['ref_range'])) {
                                                ReferenceRange::firstOrCreate([
                                                    'panel_panel_item_id' => $panelPanelItem->id,
                                                    'value' => $refRange['ref_range'],
                                                ]);
                                            }
                                        }
                                    }

                                    break; // Exit the loop since we found a match
                                }
                            }

                            // If no match found, create new panel item
                            if (!$matchFound) {
                                Log::info("No match found, creating new panel item", [
                                    'old_item_name' => $oldItem['name']
                                ]);

                                $newPanelItem = PanelItem::firstOrCreate([
                                    'lab_id' => $this->labId,
                                    'name' => $oldItem['name'],
                                ], [
                                    'code' => substr($oldItem['name'], 0, 3),
                                    'decimal_point' => $oldItem['decimal_point'] ?? 0,
                                    'unit' => $oldItem['unit'] ?? '',
                                ]);

                                // Attach to panel
                                $existingPanel->panelItems()->syncWithoutDetaching([$newPanelItem->id]);

                                // Get the newly created panel_panel_item relationship
                                $newPanelPanelItem = PanelPanelItem::where('panel_id', $panel_id)
                                    ->where('panel_item_id', $newPanelItem->id)
                                    ->first();

                                if ($newPanelPanelItem) {
                                    // Create bridge mapping
                                    $bridge = PanelBridge::firstOrCreate([
                                        'panel_panel_item_id' => $newPanelPanelItem->id,
                                        'old_panel_id' => $old_panel_id,
                                        'old_panel_item_id' => $old_panel_item_id,
                                    ]);

                                    if ($bridge->wasRecentlyCreated) {
                                        $createdBridges++;
                                        Log::info("Bridge created for new item", [
                                            'bridge_id' => $bridge->id,
                                            'new_panel_item' => $oldItem['name']
                                        ]);
                                    }

                                    // Process reference ranges
                                    foreach ($oldItem['reference_ranges'] as $refRange) {
                                        if (!empty($refRange['ref_range']) && $refRange['ref_range'] !== '0.0 - 0.0') {
                                            ReferenceRange::firstOrCreate([
                                                'panel_panel_item_id' => $newPanelPanelItem->id,
                                                'value' => $refRange['ref_range'],
                                                'description' => $refRange['range_desc'],
                                            ]);
                                        }
                                    }
                                }
                            }

                            $processedItems++;
                        } catch (Exception $itemException) {
                            Log::error("Failed to process panel item", [
                                'old_panel_item_id' => $oldItem['id'],
                                'item_name' => $oldItem['name'],
                                'error' => $itemException->getMessage()
                            ]);
                        }
                    }

                    $processedPanels++;
                } catch (Exception $panelException) {
                    Log::error("Failed to process panel", [
                        'old_panel_id' => $panel['id'],
                        'panel_code' => $panel['code'],
                        'error' => $panelException->getMessage()
                    ]);
                    continue;
                }
            }

            Log::info("Panels import completed", [
                'processed_panels' => $processedPanels,
                'processed_items' => $processedItems,
                'created_bridges' => $createdBridges
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Panels imported successfully',
                'statistics' => [
                    'total_panels' => count($panels),
                    'processed_panels' => $processedPanels,
                    'processed_items' => $processedItems,
                    'created_bridges' => $createdBridges
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Critical error in panels import', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Critical error processing panels: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function results()
    {
        try {
            $baseUrl = rtrim(env('MYHEALTH_API_URL'), '/');
            $token = env('BLOOD_STREAM_V1_TOKEN');

            if (!$baseUrl || !$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'API URL or token not configured'
                ], 500);
            }

            Log::info('Starting test results synchronization from: ' . $baseUrl);

            // Step 1: Get metadata
            $metadataResponse = Http::withHeaders([
                'Authorization' => $token
            ])
                ->timeout(30)
                ->get($baseUrl . '/api/results/metadata');


            if (!$metadataResponse->successful()) {
                throw new Exception('Failed to fetch metadata: ' . $metadataResponse->body());
            }

            $metadata = $metadataResponse->json();
            $totalRecords = $metadata['test_results_count'];
            $chunkSize = 5; // Get only first 5 results for testing

            Log::info("Total test results available: {$totalRecords}, fetching first {$chunkSize} results");

            // Step 2: Fetch all data in chunks
            $offset = 0;
            $allResults = collect();
            $processedCount = 0;

            // Get only the first chunk (first 5 results)
            Log::info("Fetching first {$chunkSize} results");

            $response = Http::withHeaders([
                'Authorization' => $token
            ])
                ->timeout(60)
                ->get($baseUrl . '/api/results/all-optimized', [
                    'chunk_size' => $chunkSize,
                    'offset' => $offset
                ]);

            if (!$response->successful()) {
                throw new Exception("Failed to fetch results: " . $response->body());
            }

            $responseData = $response->json();
            $chunk = collect($responseData['data']);

            $allResults = $allResults->merge($chunk);
            $processedCount += $chunk->count();

            // Process the chunk
            $this->processTestResults($chunk->toArray());

            Log::info("Fetched and processed {$processedCount} results");

            Log::info("Test results sync completed. Total records fetched: " . $allResults->count());

            return response()->json([
                'success' => true,
                'message' => 'First 5 test results fetched successfully',
                'statistics' => [
                    'total_available' => $totalRecords,
                    'fetched_records' => $allResults->count(),
                    'processed_records' => $processedCount
                ],
                'data' => $allResults->toArray()
            ]);
        } catch (Exception $e) {
            Log::error('Failed to sync test results', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync test results: ' . $e->getMessage()
            ], 500);
        }
    }

    private function processTestResults(array $testResults)
    {
        foreach ($testResults as $testResultData) {
            try {
                Log::info("Processing test result with refid: " . ($testResultData['refid'] ?? 'N/A'));

                // Store the test result
                $testResult = $this->storeTestResult($testResultData);

                // Store the test result items if they exist
                if (isset($testResultData['result_items']) && is_array($testResultData['result_items'])) {
                    $this->storeTestResultItems($testResult->id, $testResultData['result_items']);
                }
            } catch (Exception $e) {
                Log::error("Failed to process test result", [
                    'refid' => $testResultData['refid'] ?? 'N/A',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                continue;
            }
        }
    }

    private function storeTestResult(array $data)
    {
        // Create or find patient using icno
        $patient = $this->createOrFindPatient($data['icno'] ?? null);

        // Map API data to model fields based on TestResult fillable attributes
        $testResultData = [
            'doctor_id' => 112, // Not provided in API, set to default
            'patient_id' => $patient ? $patient->id : null,
            'ref_id' => $data['refid'] ?? null,
            'bill_code' => $data['bill_code'] ?? null,
            'lab_no' => $data['lab_no'] ?? null,
            'panel_profile_id' => null, // Not provided in API, set to null
            'is_tagon' => false, // Default value
            'collected_date' => $data['collected_date'] ?? null,
            'received_date' => $data['received_date'] ?? null,
            'reported_date' => $data['reported_date'] ?? null,
            'is_completed' => false,
            'validated_by' => null, // Not provided in API, set to null
        ];

        // Create new test result (ignore id from API data)
        $testResult = TestResult::create($testResultData);

        Log::info("Test result created", [
            'id' => $testResult->id,
            'ref_id' => $testResult->ref_id,
            'patient_id' => $patient ? $patient->id : null,
            'patient_icno' => $patient ? $patient->icno : null
        ]);

        return $testResult;
    }

    private function storeTestResultItems(int $testResultId, array $resultItems)
    {
        foreach ($resultItems as $itemData) {
            try {
                // Look up panel_panel_item_id using PanelBridge
                $panelBridge = PanelBridge::where('old_panel_id', $itemData['panel_id'] ?? null)
                    ->where('old_panel_item_id', $itemData['panel_item_id'] ?? null)
                    ->first();

                if (!$panelBridge) {
                    Log::warning("PanelBridge not found for test result item", [
                        'old_panel_id' => $itemData['panel_id'] ?? null,
                        'old_panel_item_id' => $itemData['panel_item_id'] ?? null,
                        'test_result_id' => $testResultId
                    ]);
                    continue;
                }

                // Map API data to TestResultItem model fields
                $testResultItemData = [
                    'test_result_id' => $testResultId,
                    'panel_panel_item_id' => $panelBridge->panel_panel_item_id,
                    'reference_range_id' => null, // Not provided in API, set to null
                    'value' => $itemData['result_value'] ?? null,
                    'flag' => $itemData['result_flag'] ?? null,
                    'test_notes' => $itemData['test_notes'] ?? null,
                    'status' => null, // Not provided in API, set to null
                    'is_completed' => false,
                ];

                // Create new test result item (ignore test_result_id from API data)
                $testResultItem = TestResultItem::create($testResultItemData);

                Log::info("Test result item created", [
                    'id' => $testResultItem->id,
                    'test_result_id' => $testResultId,
                    'panel_panel_item_id' => $panelBridge->panel_panel_item_id,
                    'old_panel_id' => $itemData['panel_id'] ?? null,
                    'old_panel_item_id' => $itemData['panel_item_id'] ?? null
                ]);
            } catch (Exception $e) {
                Log::error("Failed to store test result item", [
                    'test_result_id' => $testResultId,
                    'old_panel_id' => $itemData['panel_id'] ?? null,
                    'old_panel_item_id' => $itemData['panel_item_id'] ?? null,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
    }

    private function createOrFindPatient(?string $icno): ?Patient
    {
        if (empty($icno)) {
            Log::warning("Empty ICNO provided, cannot create patient");
            return null;
        }

        // Check if patient already exists
        $existingPatient = Patient::where('icno', $icno)->first();
        if ($existingPatient) {
            Log::info("Patient found", [
                'patient_id' => $existingPatient->id,
                'icno' => $icno
            ]);
            return $existingPatient;
        }

        try {
            // Use checkIcno helper to extract patient data
            $icnoData = checkIcno($icno);

            // Create patient data based on Patient fillable attributes
            $patientData = [
                'icno' => $icnoData['icno'],
                'ic_type' => $icnoData['type'],
                'name' => null, // Not provided in API
                'dob' => null, // Could be calculated from ICNO but not implemented yet
                'age' => $icnoData['age'],
                'gender' => $icnoData['gender'],
                'tel' => null, // Not provided in API
            ];

            $patient = Patient::create($patientData);

            Log::info("Patient created", [
                'patient_id' => $patient->id,
                'icno' => $icno,
                'ic_type' => $icnoData['type'],
                'gender' => $icnoData['gender'],
                'age' => $icnoData['age']
            ]);

            return $patient;
        } catch (Exception $e) {
            Log::error("Failed to create patient", [
                'icno' => $icno,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}

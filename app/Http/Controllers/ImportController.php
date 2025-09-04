<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API\Innoquest\PanelResultsController;
use Exception;
use App\Http\Controllers\Controller;
use App\Http\Requests\InnoquestResultRequest;
use App\Imports\BaseCodeMappingImport;
use App\Imports\CodeMappingImport;
use App\Imports\FileImport;
use App\Imports\Innoquest\PanelSequenceImport;
use App\Models\DeliveryFile;
use App\Models\Lab;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Validators\ValidationException;
use Illuminate\Http\Request;
use SplFileInfo;

class ImportController extends Controller
{
    protected $labId;
    protected $disk;

    public function __construct()
    {
        $this->disk = Storage::disk('sftp');
        $this->labId = 2;
    }

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

            $startTime = microtime(true);

            // Reset file status for new import session
            BaseCodeMappingImport::resetFileStatus();

            // Process each Excel file (sorted oldest first)
            foreach ($excelFiles as $fileIndex => $file) {
                $filename = $file->getFilename();
                $fileDate = date('Y-m-d H:i:s', $file->getMTime());

                try {
                    // Use the new CodeMappingImport with dynamic sheet detection
                    $codeMappingImport = new CodeMappingImport();
                    $codeMappingImport->import($file->getPathname());

                    $processedFiles++;

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

                    // Continue processing other files instead of stopping
                    continue;
                }
            }

            $processingTime = round(microtime(true) - $startTime, 2);

            // Log comprehensive summary
            Log::info('Innoquest Code Mapping Import Summary', [
                'total_files' => $totalFiles,
                'processed_files' => $processedFiles,
                'failed_files' => count($errors),
                'success_rate' => $totalFiles > 0 ? round(($processedFiles / $totalFiles) * 100, 1) . '%' : '0%',
                'processing_time' => $processingTime . 's'
            ]);

            // Log errors if any
            if (!empty($errors)) {
                Log::warning('Files that failed to import', $errors);
            }

            // Prepare response based on results
            $message = "Processed {$processedFiles} out of {$totalFiles} Excel files";

            if (!empty($errors)) {
                $message .= " with " . count($errors) . " errors";
            }

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

    public function innoquestPanelSequence()
    {
        $processedFiles = 0;
        $totalFiles = 0;
        $errors = [];

        try {

            // Get file from the public/files directory
            $directory = public_path('files');
            $filename  = 'IQMY-Alpro Panels Order List 13Aug2025 ver1.0-NS.xlsx';
            $targetFile = $directory . '/' . $filename;

            $errors = [];
            $processedFiles = 0;

            $startTime = microtime(true);

            if (File::exists($targetFile) && !str_starts_with($filename, '~$')) {
                $file = new SplFileInfo($targetFile);
                $fileDate = date('Y-m-d H:i:s', $file->getMTime());

                try {
                    // Run your import
                    $panelSequenceImport = new PanelSequenceImport();
                    Excel::import($panelSequenceImport, $file->getPathname());

                    $processedFiles++;
                } catch (\Exception $e) {
                    $error = [
                        'file'      => $filename,
                        'error'     => $e->getMessage(),
                        'line'      => $e->getLine(),
                        'file_path' => $e->getFile(),
                        'file_date' => $fileDate,
                    ];

                    $errors[] = $error;
                }
            } else {
                // Handle case: file not found or it was a temp Excel file
                $errors[] = [
                    'file'  => $filename,
                    'error' => 'File not found or is a temp file.'
                ];
            }

            $totalFiles = 1; // Set to 1 since we're processing a single file
            $processingTime = round(microtime(true) - $startTime, 2);

            // Log comprehensive summary
            Log::info('Innoquest Panel Sequence Import Summary', [
                'target_file' => $filename,
                'processed_files' => $processedFiles,
                'failed_files' => count($errors),
                'success_rate' => $processedFiles > 0 ? '100%' : '0%',
                'processing_time' => $processingTime . 's'
            ]);

            // Log errors if any
            if (!empty($errors)) {
                Log::warning('Panel sequence file failed to import', $errors);
            }

            // Prepare response based on results
            $message = $processedFiles > 0
                ? "Successfully processed panel sequence file: {$filename}"
                : "Failed to process panel sequence file: {$filename}";

            if (!empty($errors)) {
                $message .= " with " . count($errors) . " errors";
            }

            return response()->json([
                'success' => $processedFiles > 0,
                'message' => $message,
                'statistics' => [
                    'target_file' => $filename,
                    'processed_files' => $processedFiles,
                    'failed_files' => count($errors),
                ],
                'errors' => $errors
            ], $processedFiles > 0 ? 200 : 500);
        } catch (Exception $e) {
            Log::error('Critical error in innoquestPanelSequence import', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Critical error processing panel sequence file: ' . $e->getMessage(),
                'statistics' => [
                    'target_file' => $filename ?? 'Unknown',
                    'processed_files' => $processedFiles,
                    'failed_files' => count($errors),
                ]
            ], 500);
        }
    }

    public function files()
    {
        // Increase execution time limit
        ini_set('max_execution_time', 300); // 5 minutes
        ini_set('memory_limit', '512M');

        $processedFiles = [];

        try {
            $path = Lab::find($this->labId)->path;
            $is_exist = $this->disk->exists('/' . $path);

            if (!$is_exist) {
                return response()->json([
                    'success' => false,
                    'message' => 'SFTP path not found',
                ], 404);
            }

            // Ensure csv directory exists
            $csvDir = storage_path('app/public/csv');
            if (!File::exists($csvDir)) {
                File::makeDirectory($csvDir, 0755, true);
                Log::info('Created csv directory: ' . $csvDir);
            }

            $files = $this->disk->allFiles($path);

            // Filter CSV files first to avoid processing non-CSV files
            $csvFiles = array_filter($files, function ($file) {
                return pathinfo($file, PATHINFO_EXTENSION) === 'csv';
            });

            Log::info('Found CSV files', ['count' => count($csvFiles), 'files' => $csvFiles]);

            foreach ($csvFiles as $file) {
                $startTime = microtime(true);
                Log::info('Processing CSV file', ['file' => $file, 'start_time' => date('Y-m-d H:i:s')]);

                try {
                    $content = $this->disk->get($file);

                    // Get file base name
                    $file_name = basename($this->disk->path($file));

                    // Move to csv storage
                    Storage::disk('public')->put("csv/{$file_name}", $content);

                    // Get new csv path
                    $temp_path = storage_path("app/public/csv/{$file_name}");

                    // Check if CSV file is empty - use more efficient method
                    $handle = fopen($temp_path, 'r');
                    $total_rows = 0;
                    if ($handle) {
                        // Skip header row
                        $header = fgetcsv($handle);
                        while (fgetcsv($handle) !== false) {
                            $total_rows++;
                        }
                        fclose($handle);
                    }

                    if ($total_rows > 0) {
                        try {
                            $import = new FileImport($file_name, $this->labId);
                            Excel::import($import, $temp_path, null, ExcelFormat::CSV);

                            // Get headings and statistics from the import
                            $headings = $import->getHeadings();
                            $stats = $import->getStats();

                            // Log summary statistics
                            Log::info('CSV Import Summary', [
                                'file' => $file_name,
                                'total_rows' => $stats['total_rows'],
                                'successful_rows' => $stats['successful_rows'],
                                'failed_rows' => $stats['failed_rows'],
                                'unique_lab_nos' => $stats['unique_lab_nos_count'],
                                'missing_panels' => $stats['missing_panels_count'],
                                'missing_panel_items' => $stats['missing_panel_items_count'],
                                'processing_time' => round(microtime(true) - $startTime, 2) . 's'
                            ]);

                            // Log missing panels if any
                            if ($stats['missing_panels_count'] > 0) {
                                Log::warning('Missing Panels', $stats['missing_panels']);
                            }

                            $processedFiles[] = [
                                'file_name' => $file_name,
                                'total_rows' => $total_rows,
                                'headings' => $headings,
                                'statistics' => $stats,
                                'status' => 'processed',
                                'processing_time' => round(microtime(true) - $startTime, 2) . 's'
                            ];
                        } catch (ValidationException $e) {
                            $processedFiles[] = [
                                'file_name' => $file_name,
                                'total_rows' => $total_rows,
                                'error' => 'Validation failed: ' . $e->getMessage(),
                                'status' => 'failed',
                                'processing_time' => round(microtime(true) - $startTime, 2) . 's'
                            ];
                        } catch (Exception $e) {
                            $processedFiles[] = [
                                'file_name' => $file_name,
                                'total_rows' => $total_rows,
                                'error' => $e->getMessage(),
                                'status' => 'failed',
                                'processing_time' => round(microtime(true) - $startTime, 2) . 's'
                            ];
                        }
                    } else {
                        $processedFiles[] = [
                            'file_name' => $file_name,
                            'total_rows' => 0,
                            'status' => 'skipped_empty',
                            'processing_time' => round(microtime(true) - $startTime, 2) . 's'
                        ];
                    }

                    Log::info('Completed processing file', [
                        'file' => $file_name,
                        'total_rows' => $total_rows,
                        'processing_time' => round(microtime(true) - $startTime, 2) . 's'
                    ]);
                } catch (Exception $fileException) {
                    $processedFiles[] = [
                        'file_name' => basename($file),
                        'error' => $fileException->getMessage(),
                        'status' => 'error',
                        'processing_time' => round(microtime(true) - $startTime, 2) . 's'
                    ];

                    Log::error('Error processing file', [
                        'file' => $file,
                        'error' => $fileException->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Files processed successfully',
                'files' => $processedFiles,
                'total_csv_files' => count($processedFiles)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing files: ' . $e->getMessage(),
                'files' => $processedFiles
            ], 500);
        }
    }

    public function json()
    {
        // Increase execution time limit
        ini_set('max_execution_time', 300); // 5 minutes
        ini_set('memory_limit', '512M');

        $processedFiles = [];

        try {
            $path = Lab::find($this->labId)->path;
            $is_exist = $this->disk->exists('/' . $path);

            if (!$is_exist) {
                return response()->json([
                    'success' => false,
                    'message' => 'SFTP path not found',
                ], 404);
            }

            // Ensure json directory exists
            $jsonDir = storage_path('app/public/json');
            if (!File::exists($jsonDir)) {
                File::makeDirectory($jsonDir, 0755, true);
                Log::info('Created json directory: ' . $jsonDir);
            }

            $files = $this->disk->allFiles($path);

            // Filter JSON files first to avoid processing non-JSON files
            $jsonFiles = array_filter($files, function ($file) {
                return pathinfo($file, PATHINFO_EXTENSION) === 'json';
            });

            Log::info('Found JSON files', ['count' => count($jsonFiles), 'files' => $jsonFiles]);

            foreach ($jsonFiles as $file) {
                $startTime = microtime(true);
                Log::info('Processing JSON file', ['file' => $file, 'start_time' => date('Y-m-d H:i:s')]);

                try {
                    $content = $this->disk->get($file);

                    // Get file base name
                    $file_name = basename($this->disk->path($file));

                    // Move to json storage
                    Storage::disk('public')->put("json/{$file_name}", $content);

                    // Get new json path
                    $temp_path = storage_path("app/public/json/{$file_name}");

                    // Validate JSON content
                    $jsonData = json_decode($content, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception('Invalid JSON format: ' . json_last_error_msg());
                    }

                    if (empty($jsonData)) {
                        $processedFiles[] = [
                            'file_name' => $file_name,
                            'status' => 'skipped_empty',
                            'processing_time' => round(microtime(true) - $startTime, 2) . 's'
                        ];
                        continue;
                    }

                    try {
                        // Create a mock request with JSON data
                        $mockRequest = Request::create('/api/v1/result/panel', 'POST', $jsonData);
                        $mockRequest->headers->set('Content-Type', 'application/json');

                        // Create InnoquestResultRequest from the mock request
                        $innoquestRequest = InnoquestResultRequest::createFrom($mockRequest);
                        $innoquestRequest->setContainer(app());
                        $innoquestRequest->setRedirector(app(\Illuminate\Routing\Redirector::class));

                        // Manually set up the validator for the FormRequest
                        $factory = app(\Illuminate\Validation\Factory::class);
                        $validator = $factory->make($jsonData, $innoquestRequest->rules());
                        $innoquestRequest->setValidator($validator);

                        // Call panelResults method from ResultController
                        $resultController = new PanelResultsController();
                        $response = $resultController->panelResults($innoquestRequest);

                        if ($response) {
                            // Get response data
                            $responseData = json_decode($response->getContent(), true);
                            $statusCode = $response->getStatusCode();

                            if ($statusCode === 200 && $responseData['success']) {
                                $processedFiles[] = [
                                    'file_name' => $file_name,
                                    'status' => 'processed',
                                    'test_result_id' => $responseData['data']['test_result_id'] ?? null,
                                    'panel' => $responseData['data']['panel'] ?? null,
                                    'processing_time' => round(microtime(true) - $startTime, 2) . 's'
                                ];

                                Log::info('JSON file processed successfully', [
                                    'file' => $file_name,
                                    'test_result_id' => $responseData['data']['test_result_id'] ?? null,
                                    'processing_time' => round(microtime(true) - $startTime, 2) . 's'
                                ]);
                            } else {
                                throw new Exception('Panel results processing failed: ' . ($responseData['message'] ?? 'Unknown error'));
                            }
                        }
                    } catch (Exception $e) {
                        $processedFiles[] = [
                            'file_name' => $file_name,
                            'error' => $e->getMessage(),
                            'status' => 'failed',
                            'processing_time' => round(microtime(true) - $startTime, 2) . 's'
                        ];

                        Log::error('Error processing JSON data', [
                            'file' => $file_name,
                            'error' => $e->getMessage()
                        ]);
                    }
                } catch (Exception $fileException) {
                    $processedFiles[] = [
                        'file_name' => basename($file),
                        'error' => $fileException->getMessage(),
                        'status' => 'error',
                        'processing_time' => round(microtime(true) - $startTime, 2) . 's'
                    ];

                    Log::error('Error processing file', [
                        'file' => $file,
                        'error' => $fileException->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'JSON files processed successfully',
                'files' => $processedFiles,
                'total_json_files' => count($processedFiles)
            ]);
        } catch (Exception $e) {
            Log::error('Critical error in JSON import', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing JSON files: ' . $e->getMessage(),
                'files' => $processedFiles
            ], 500);
        }
    }

    public function deliveryFiles()
    {
        // Increase execution time limit
        ini_set('max_execution_time', 300); // 5 minutes
        ini_set('memory_limit', '512M');

        $processedFiles = [];

        try {
            $files = DeliveryFile::all()->pluck('json_content')->toArray();
            foreach ($files as $jsonContent) {
                try {
                    $startTime = microtime(true);

                    // Decode JSON content to array
                    $jsonData = json_decode($jsonContent, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception('Invalid JSON content: ' . json_last_error_msg());
                    }

                    // Create a mock request with JSON data
                    $mockRequest = Request::create('/api/v1/result/panel', 'POST', $jsonData);
                    $mockRequest->headers->set('Content-Type', 'application/json');

                    // Create InnoquestResultRequest from the mock request
                    $innoquestRequest = InnoquestResultRequest::createFrom($mockRequest);
                    $innoquestRequest->setContainer(app());
                    $innoquestRequest->setRedirector(app(\Illuminate\Routing\Redirector::class));

                    // Manually set up the validator for the FormRequest
                    $factory = app(\Illuminate\Validation\Factory::class);
                    $validator = $factory->make($jsonData, $innoquestRequest->rules());
                    $innoquestRequest->setValidator($validator);

                    // Call panelResults method from ResultController
                    $resultController = new PanelResultsController();
                    $response = $resultController->panelResults($innoquestRequest);

                    if ($response) {
                        // Get response data
                        $responseData = json_decode($response->getContent(), true);
                        $statusCode = $response->getStatusCode();

                        if ($statusCode === 200 && $responseData['success']) {
                            $processedFiles[] = [
                                'jsonData' => $jsonData,
                                'status' => 'processed',
                                'test_result_id' => $responseData['data']['test_result_id'] ?? null,
                                'panel' => $responseData['data']['panel'] ?? null,
                                'processing_time' => round(microtime(true) - $startTime, 2) . 's'
                            ];

                            Log::info('JSON file processed successfully', [
                                'jsonData' => $jsonData,
                                'test_result_id' => $responseData['data']['test_result_id'] ?? null,
                                'processing_time' => round(microtime(true) - $startTime, 2) . 's'
                            ]);
                        } else {
                            throw new Exception('Panel results processing failed: ' . ($responseData['message'] ?? 'Unknown error'));
                        }
                    }
                } catch (Exception $e) {
                    $processedFiles[] = [
                        'jsonData' => $jsonData ?? 'Unknown',
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                        'processing_time' => isset($startTime) ? round(microtime(true) - $startTime, 2) . 's' : '0s'
                    ];

                    Log::error('Error processing JSON data', [
                        'jsonData' => $jsonData ?? 'Unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Delivery files processed successfully',
                'files' => $processedFiles,
                'total_files' => count($processedFiles)
            ]);
        } catch (Exception $e) {
            Log::error('Critical error in deliveryFiles import', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing delivery files: ' . $e->getMessage(),
                'files' => $processedFiles
            ], 500);
        }
    }
}
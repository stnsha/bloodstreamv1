<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Imports\BaseCodeMappingImport;
use App\Imports\CodeMappingImport;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class ImportController extends Controller
{
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
}

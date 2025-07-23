<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Imports\CodeMappingImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class ImportController extends Controller
{
    public static function innoquestCodeMapping()
    {
        try {
            // Get all Excel files from the public/files directory
            $directory = public_path('files');
            $files = File::files($directory);

            // Filter for Excel files only
            $excelFiles = array_filter($files, function ($file) {
                return in_array($file->getExtension(), ['xlsx', 'xls', 'csv']);
            });

            if (empty($excelFiles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Excel files found in public/files directory'
                ], 404);
            }

            // Process each Excel file
            foreach ($excelFiles as $file) {
                try {
                    $import = new CodeMappingImport();
                    $import->onlySheets('2. Profile Code', '3. Doctor Code', '4. Tag On', '5. Reported Test', '6. Bill Code');
                    Excel::import($import, $file->getPathname());

                    Log::info('Successfully imported file: ' . $file->getFilename());
                } catch (\Exception $e) {
                    Log::error('Error importing file: ' . $file->getFilename(), [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // return response()->json([
            //     'success' => true,
            //     'message' => 'Processed ' . count($excelFiles) . ' Excel files'
            // ]);
        } catch (\Exception $e) {
            Log::error('Error in innoquestCodeMapping import', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing Excel files: ' . $e->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Imports\CodeMappingImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class ImportController extends Controller
{
    public static function innoquestCodeMapping()
    {
        try {
            // Path to the Excel file in public/files
            $filePath = public_path('files/IQMY-Alpro Code Mapping Document (staging) 18July2025-ver0.2-NS.xlsx');

            // Check if file exists
            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found in public/files directory'
                ], 404);
            }

            $import = new CodeMappingImport();
            $import->onlySheets('2. Profile Code', '3. Doctor Code', '5. Reported Test', '6. Bill Code');
            Excel::import($import, $filePath);
            // return response()->json(200, ['message' => 'Excel data successfully imported.']);
        } catch (\Exception $e) {
            Log::error('Error in innoquestCodeMapping import', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // return response()->json([
            //     'success' => false,
            //     'message' => 'Import failed: ' . $e->getMessage()
            // ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Imports\BillCodeImport;
use App\Imports\CodeMappingImport;
use App\Imports\DoctorCodeImport;
use App\Imports\ProfileCodeImport;
use App\Imports\ReportedTestImport;
use App\Imports\TagOnImport;
use App\Imports\SequentialProfileCodeImport;
use App\Imports\SequentialTagOnImport;
use App\Imports\SequentialReportedTestImport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    public static function innoquestCodeMapping()
    {
        try {
            // Get all Excel files from the public/files directory
            $directory = public_path('files');
            $files = File::files($directory);

            // Filter for Excel files only, exclude temporary files
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
            Log::info('Found ' . count($excelFiles) . ' Excel files to process');

            foreach ($excelFiles as $file) {
                try {
                    Log::info('Processing file: ' . $file->getFilename());

                    // Sequential import with strict dependency order using CodeMappingImport
                    // STEP 1: Profile Code (Sheet 2) - Creates Panel records with PK
                    $profileCodeMapping = new CodeMappingImport();
                    $profileCodeMapping->onlySheets('2. Profile Code');
                    Excel::import($profileCodeMapping, $file->getPathname());

                    // STEP 2: Tag On (Sheet 4) - Creates PanelTag records using Panel PK as FK  
                    $tagOnMapping = new CodeMappingImport();
                    $tagOnMapping->onlySheets('4. Tag On');
                    Excel::import($tagOnMapping, $file->getPathname());

                    // STEP 3: Reported Test (Sheet 5) - Depends on both Panel and PanelTag
                    $reportedTestMapping = new CodeMappingImport();
                    $reportedTestMapping->onlySheets('5. Reported Test');
                    Excel::import($reportedTestMapping, $file->getPathname());

                    // Independent imports can run separately (no FK dependencies)
                    // Doctor Code (Sheet 3) - Independent
                    $doctorCodeMapping = new CodeMappingImport();
                    $doctorCodeMapping->onlySheets('3. Doctor Code');
                    Excel::import($doctorCodeMapping, $file->getPathname());

                    // Bill Code (Sheet 6) - Independent  
                    $billCodeMapping = new CodeMappingImport();
                    $billCodeMapping->onlySheets('6. Bill Code');
                    Excel::import($billCodeMapping, $file->getPathname());

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

<?php

namespace App\Imports;

use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Exception;

class CodeMappingImport
{
    private array $sheetMappings = [
        // Pattern matching for sheet names (case-insensitive)
        'profile.*code' => ProfileCodeImport::class,
        'doctor.*code' => DoctorCodeImport::class,
        'tag.*on.*ton' => TagOnImport::class,  // Tag On (TON)
        'tag.*on.*qon' => TagOnImport::class,  // Tag On (QON)
        'tag.*on' => TagOnImport::class,       // Generic Tag On
        'reported.*test' => ReportedTestImport::class,
        'bill.*code' => BillCodeImport::class,
        'department.*code' => null, // Ignore department code as requested
    ];

    private array $importOrder = [
        'profile.*code',    // Creates Panel records (primary key)
        'tag.*on.*ton',     // Tag On (TON) - Creates PanelTag records
        'tag.*on.*qon',     // Tag On (QON) - Creates PanelTag records  
        'tag.*on',          // Generic Tag On - Creates PanelTag records
        'reported.*test',   // Depends on both Panel and PanelTag
        'doctor.*code',     // Independent
        'bill.*code',       // Independent
    ];

    public function import(string $filePath): void
    {
        try {
            // Get all sheet names from the Excel file
            $spreadsheet = IOFactory::load($filePath);
            $sheetNames = $spreadsheet->getSheetNames();
            if (empty($sheetNames)) {
                throw new Exception('No sheets found in Excel file');
            }

            Log::info('Found sheets in Excel file:', $sheetNames);

            // Detect available sheets and their types
            $availableSheets = $this->detectSheetTypes($sheetNames);
            Log::info('Detected sheet mappings:', $availableSheets);

            // Import sheets in dependency order
            $this->importSheetsInOrder($filePath, $availableSheets);
        } catch (Exception $e) {
            Log::error('CodeMappingImport failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function detectSheetTypes(array $sheetNames): array
    {
        $detectedSheets = [];

        foreach ($sheetNames as $sheetName) {
            $normalizedName = strtolower(trim($sheetName));

            foreach ($this->sheetMappings as $pattern => $importClass) {
                if (preg_match('/' . $pattern . '/i', $normalizedName)) {
                    if ($importClass !== null) {
                        $detectedSheets[$pattern] = [
                            'sheet_name' => $sheetName,
                            'import_class' => $importClass
                        ];
                    } else {
                        Log::info("Ignoring sheet '{$sheetName}' as configured");
                    }
                    break; // Found match, stop checking other patterns
                }
            }
        }

        return $detectedSheets;
    }

    private function importSheetsInOrder(string $filePath, array $availableSheets): void
    {
        foreach ($this->importOrder as $sheetPattern) {
            if (isset($availableSheets[$sheetPattern])) {
                $sheetData = $availableSheets[$sheetPattern];
                $this->importSingleSheet($filePath, $sheetData['sheet_name'], $sheetData['import_class']);
            } else {
                Log::warning("Sheet pattern '{$sheetPattern}' not found in Excel file, skipping");
            }
        }
    }

    private function importSingleSheet(string $filePath, string $sheetName, string $importClass): void
    {
        try {
            Log::info("Starting import for sheet: {$sheetName} using {$importClass}");

            $import = new $importClass();

            // Create a wrapper import class that selects only the specific sheet
            $sheetSpecificImport = new class($import, $sheetName) implements \Maatwebsite\Excel\Concerns\WithMultipleSheets {
                private $import;
                private $sheetName;

                public function __construct($import, $sheetName)
                {
                    $this->import = $import;
                    $this->sheetName = $sheetName;
                }

                public function sheets(): array
                {
                    return [
                        $this->sheetName => $this->import
                    ];
                }
            };

            Excel::import($sheetSpecificImport, $filePath);

            Log::info("Successfully completed import for sheet: {$sheetName}");
        } catch (Exception $e) {
            Log::error("Failed to import sheet: {$sheetName}", [
                'import_class' => $importClass,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new Exception("Import failed for sheet '{$sheetName}': " . $e->getMessage(), 0, $e);
        }
    }

    public function getSupportedSheetTypes(): array
    {
        return array_keys($this->sheetMappings);
    }

    public function getImportOrder(): array
    {
        return $this->importOrder;
    }
}

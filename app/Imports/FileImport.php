<?php

namespace App\Imports;

use App\Models\Lab;
use App\Models\Panel;
use App\Models\PanelItem;
use App\Models\PanelPanelItem;
use App\Models\Patient;
use App\Models\ReferenceRange;
use App\Models\TestResult;
use App\Models\TestResultItem;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class FileImport implements ToModel, WithHeadingRow, WithValidation, WithChunkReading, WithBatchInserts
{
    use Importable, SkipsFailures;

    protected $fileName, $labId, $headings;
    protected static $testResultCache = [];
    protected static $stats = [
        'total_rows' => 0,
        'successful_rows' => 0,
        'unique_lab_nos' => [],
        'missing_panels' => [],
        'missing_panel_items' => []
    ];

    public function __construct($fileName = null, $labId = null)
    {
        $this->fileName = $fileName;
        $this->labId = $labId;
        $this->headings = [];

        // Reset stats for new file
        self::$stats = [
            'total_rows' => 0,
            'successful_rows' => 0,
            'unique_lab_nos' => [],
            'missing_panels' => [],
            'missing_panel_items' => []
        ];
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        ini_set('memory_limit', '4096M');

        try {
            // Store headings from the first row processed
            if (empty($this->headings)) {
                $this->headings = array_keys($row);
            }

            $row = $this->prepareForImport($row);
            self::$stats['total_rows']++;

            // Track unique lab_no
            if (!empty($row['labno']) && !in_array($row['labno'], self::$stats['unique_lab_nos'])) {
                self::$stats['unique_lab_nos'][] = $row['labno'];
            }

            DB::beginTransaction();

            // Create or find patient
            $patient = $this->createOrFindPatient($row['icno'] ?? null);
            if (!$patient) {
                DB::rollback();
                return null;
            }

            // Create or find test result
            $testResult = $this->createOrFindTestResult($row, $patient->id);
            if (!$testResult) {
                DB::rollback();
                return null;
            }

            // Process panel and panel item
            $panelPanelItemId = $this->processPanelData($row);
            if (!$panelPanelItemId) {
                DB::rollback();
                return null;
            }

            // Create test result item
            $this->createTestResultItem($testResult->id, $panelPanelItemId, $row);

            DB::commit();
            self::$stats['successful_rows']++;

            return null; // We're not creating a specific model, just processing data

        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Error processing row in FileImport', [
                'error' => $e->getMessage(),
                'file' => $this->fileName,
                'lab_no' => $row['labno'] ?? 'N/A',
                'panel' => $row['panel'] ?? 'N/A'
            ]);
            return null;
        }
    }

    public function prepareForImport($row)
    {
        $row = $this->checkNull($row);

        $row['icno'] = str_replace('-', '', $row['icno']);
        $row['refid'] = $this->formatRefid($row['refid']);

        $row['collecteddate'] = $row['collecteddate'] != null ? $this->formatDatetime($row['collecteddate']) : null;
        $row['receiveddate'] = $row['receiveddate'] != null ? $this->formatDatetime($row['receiveddate']) : null;
        $row['reporteddate'] = $row['reporteddate'] != null ? $this->formatDatetime($row['reporteddate']) : null;
        // $row['refrange'] = $row['refrange'] != null ? $this->fixRefrange($row['refrange']) : null;
        $row['refrange'] = $row['refrange'] != null ? str_replace("to", "-", $row['refrange']) : null;

        return $row;
    }

    public function rules(): array
    {
        return [
            'icno' => ['required'],
            'refid' => ['nullable'],
            'labno' => ['required'],
            'panel' => ['required'],
            'panelname' => ['required'],
            'collecteddate' => ['nullable'],
            'receiveddate' => ['nullable'],
            'reporteddate' => ['nullable'],
            'sequenceno' => ['nullable'],
            'ordertype' => ['nullable'],
            'ordername' => ['required'],
            'decimalpoint' => ['nullable'],
            'resultvalue' => ['nullable'],
            'resultflag' => ['nullable'],
            'refrange' => ['nullable'],
            'unit' => ['nullable'],
            'testnotes' => ['nullable'],
            'overallnotes' => ['nullable'],
            'rangedesc' => ['nullable'],
        ];
    }

    /**
     * Set chunk size to reduce memory usage
     */
    public function chunkSize(): int
    {
        return 500; // Larger chunks for better performance with big files
    }

    public function batchSize(): int
    {
        return 250; // Larger batches for fewer database calls
    }

    protected function formatDate($date)
    {
        $value = str_replace(['.', '/'], '-', $date);
        $final_date = Carbon::parse($value)->format('Y-m-d');
        return $final_date;
    }

    protected function formatDatetime($date)
    {
        $value = str_replace(['.', '/'], '-', $date);
        $final_date = Carbon::parse($value)->format('Y-m-d H:i:s');
        return $final_date;
    }

    protected function fixRefrange($value)
    {
        if ($value === null) {
            return null;
        } elseif (preg_match('/^(\d+)-([A-Za-z]{3})$/', $value, $matches)) {
            $number = $matches[1];
            $month = $matches[2];

            $monthMap = [
                'Jan' => '1',
                'Feb' => '2',
                'Mar' => '3',
                'Apr' => '4',
                'May' => '5',
                'Jun' => '6',
                'Jul' => '7',
                'Aug' => '8',
                'Sep' => '9',
                'Oct' => '10',
                'Nov' => '11',
                'Dec' => '12',
            ];

            if (array_key_exists($month, $monthMap)) {
                $monthNumber = $monthMap[$month];
                return "( {$number} - {$monthNumber} )";
            }
        }

        return $value;
    }

    protected function checkNull($row)
    {
        foreach ($row as $key => $value) {
            if (is_string($value) && strtoupper($value) === 'NULL') {
                $row[$key] = null;
            }
        }

        return $row;
    }

    protected function formatRefid($refid)
    {
        $code = Lab::find($this->labId)->code;

        if (!$code || empty($refid)) {
            return null;
        }

        $refid = strtoupper($refid);
        $refid = preg_replace('/[^A-Z0-9]/', '', $refid);

        if (preg_match('/\b' . preg_quote($code, '/') . '(\d{5})\b/', $refid, $matches)) {
            return $code . $matches[1];
        }

        return null;
    }

    /**
     * Create or find patient using ICNO (similar to results() method)
     */
    protected function createOrFindPatient(?string $icno): ?Patient
    {
        if (empty($icno)) {
            return null;
        }

        // Check if patient already exists
        $existingPatient = Patient::where('icno', $icno)->first();
        if ($existingPatient) {
            return $existingPatient;
        }

        try {
            // Use checkIcno helper to extract patient data
            $icnoData = checkIcno($icno);

            // Create patient data based on Patient fillable attributes
            $patientData = [
                'icno' => $icnoData['icno'],
                'ic_type' => $icnoData['type'],
                'name' => null, // Not provided in CSV
                'dob' => null, // Could be calculated from ICNO but not implemented yet
                'age' => $icnoData['age'],
                'gender' => $icnoData['gender'],
                'tel' => null, // Not provided in CSV
            ];

            $patient = Patient::create($patientData);
            return $patient;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create or find test result (similar to results() method)
     */
    protected function createOrFindTestResult(array $row, int $patientId): ?TestResult
    {
        $labNo = $row['labno'] ?? null;
        if (empty($labNo)) {
            return null;
        }

        // Check cache first to avoid duplicate test results for same lab_no
        if (isset(self::$testResultCache[$labNo])) {
            return self::$testResultCache[$labNo];
        }

        // Check if test result already exists
        $existingTestResult = TestResult::where('lab_no', $labNo)->first();
        if ($existingTestResult) {
            self::$testResultCache[$labNo] = $existingTestResult;
            return $existingTestResult;
        }

        // Map CSV data to model fields based on TestResult fillable attributes
        $testResultData = [
            'doctor_id' => 112, // Default - Alpro Clinic Centrio
            'patient_id' => $patientId,
            'ref_id' => $row['refid'] ?? null,
            'bill_code' => $row['billcode'] ?? null,
            'lab_no' => $labNo,
            'panel_profile_id' => null, // Not provided in CSV
            'is_tagon' => false, // Default value
            'collected_date' => $row['collecteddate'] ?? null,
            'received_date' => $row['receiveddate'] ?? null,
            'reported_date' => $row['reporteddate'] ?? null,
            'is_completed' => true,
            'validated_by' => null, // Not provided in CSV
        ];

        // Create new test result
        $testResult = TestResult::create($testResultData);

        // Cache the result
        self::$testResultCache[$labNo] = $testResult;

        return $testResult;
    }

    /**
     * Process panel data (similar to panels() method logic)
     */
    protected function processPanelData(array $row): ?int
    {
        try {
            $panelCode = $row['panel'] ?? null;
            $panelName = $row['panelname'] ?? null;
            $panelItemName = $row['ordername'] ?? null;

            if (empty($panelCode) || empty($panelItemName)) {
                return null;
            }

            // Find existing panel by code first
            $existingPanel = Panel::with('panelItems')->where('code', $panelCode)->first();

            // If not found by code, try searching by int_code
            if (!$existingPanel) {
                $existingPanel = Panel::with('panelItems')->where('int_code', $panelCode)->first();
            }

            if (!$existingPanel) {
                // Track missing panels
                $missingPanel = $panelCode . ' (' . $panelName . ')';
                if (!in_array($missingPanel, self::$stats['missing_panels'])) {
                    self::$stats['missing_panels'][] = $missingPanel;
                }
                return null;
            }

            $panelId = $existingPanel->id;
            $matchFound = false;
            $panelPanelItemId = null;

            // Try to match with existing panel items
            foreach ($existingPanel->panelItems as $currentPanelItem) {
                $normalized1 = preg_replace('/[[:punct:]\s]+/', '', strtolower($currentPanelItem->name));
                $normalized2 = preg_replace('/[[:punct:]\s]+/', '', strtolower($panelItemName));

                if (strcasecmp($normalized1, $normalized2) === 0) {
                    $matchFound = true;

                    // Get the panel_panel_item relationship
                    $panelPanelItem = PanelPanelItem::where('panel_id', $panelId)
                        ->where('panel_item_id', $currentPanelItem->id)
                        ->first();

                    if ($panelPanelItem) {
                        $panelPanelItemId = $panelPanelItem->id;
                    }

                    break; // Exit the loop since we found a match
                }
            }

            // If no match found, create new panel item
            if (!$matchFound) {
                $newPanelItem = PanelItem::firstOrCreate([
                    'lab_id' => $this->labId,
                    'name' => $panelItemName,
                ], [
                    'code' => substr($panelItemName, 0, 3),
                    'decimal_point' => $row['decimalpoint'] ?? 0,
                    'unit' => $row['unit'] ?? '',
                ]);

                // Attach to panel
                $existingPanel->panelItems()->syncWithoutDetaching([$newPanelItem->id]);

                // Get the newly created panel_panel_item relationship
                $newPanelPanelItem = PanelPanelItem::where('panel_id', $panelId)
                    ->where('panel_item_id', $newPanelItem->id)
                    ->first();

                if ($newPanelPanelItem) {
                    $panelPanelItemId = $newPanelPanelItem->id;
                }
            }

            return $panelPanelItemId;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Create test result item (similar to results() method)
     */
    protected function createTestResultItem(int $testResultId, int $panelPanelItemId, array $row): void
    {
        try {
            // Determine the value: if resultvalue is null and testnotes is not null, use testnotes as value
            $resultValue = $row['resultvalue'] ?? null;
            $testNotes = $row['testnotes'] ?? null;

            if (empty($resultValue) && !empty($testNotes)) {
                $resultValue = $testNotes;
                // Clear testnotes since we've moved it to value
                $testNotes = null;
            }

            // Find or create reference range if refrange is filled
            $referenceRangeId = null;
            if (filled($row['refrange'])) {
                $referenceRange = ReferenceRange::firstOrCreate([
                    'panel_panel_item_id' => $panelPanelItemId,
                    'value' => $row['refrange'],
                ], [
                    'description' => $row['rangedesc'] ?? null,
                ]);

                $referenceRangeId = $referenceRange->id;
            }

            // Map CSV data to TestResultItem model fields
            $testResultItemData = [
                'test_result_id' => $testResultId,
                'panel_panel_item_id' => $panelPanelItemId,
                'reference_range_id' => $referenceRangeId,
                'value' => $resultValue,
                'flag' => $row['resultflag'] ?? null,
                'test_notes' => $testNotes,
                'status' => null, // Not provided in CSV
                'is_completed' => false,
            ];

            // Create new test result item
            TestResultItem::create($testResultItemData);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Get the headings from the CSV file
     */
    public function getHeadings(): array
    {
        return $this->headings;
    }

    /**
     * Get import statistics
     */
    public function getStats(): array
    {
        return [
            'total_rows' => self::$stats['total_rows'],
            'successful_rows' => self::$stats['successful_rows'],
            'failed_rows' => self::$stats['total_rows'] - self::$stats['successful_rows'],
            'unique_lab_nos_count' => count(self::$stats['unique_lab_nos']),
            'missing_panels_count' => count(self::$stats['missing_panels']),
            'missing_panel_items_count' => count(self::$stats['missing_panel_items']),
            'missing_panels' => self::$stats['missing_panels'],
            'missing_panel_items' => self::$stats['missing_panel_items'],
        ];
    }
}
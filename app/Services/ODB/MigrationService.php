<?php

namespace App\Services\ODB;

use App\Models\Doctor;
use App\Models\Eurofins\ReportRecord;
use App\Models\MasterPanel;
use App\Models\MasterPanelComment;
use App\Models\MasterPanelItem;
use App\Models\Panel;
use App\Models\PanelCategory;
use App\Models\PanelComment;
use App\Models\PanelItem;
use App\Models\PanelProfile;
use App\Models\Patient;
use App\Models\ReferenceRange;
use App\Models\TestResult;
use App\Models\TestResultComment;
use App\Models\TestResultItem;
use App\Models\TestResultProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class MigrationService
{
    const LAB_ID = 5; // Eurofins Malaysia

    /**
     * Process a single report with its parameters
     */
    public function processReport($report, $parameters)
    {
        $refId = $report['ref_id'] ?? 'unknown';
        $totalParams = count($parameters);

        Log::channel('migrate-log')->info('=== Starting report processing ===', [
            'ref_id' => $refId,
            'total_parameters' => $totalParams
        ]);

        DB::beginTransaction();

        try {
            // 1. Find or create patient
            $patientId = $this->findOrCreatePatient($report);

            // 2. Find or create doctor
            $doctorId = $this->findOrCreateDoctor($report);

            // 3. Create or update test result
            $testResult = $this->createTestResult($report, $patientId, $doctorId);

            // 4. Process parameters hierarchically
            $this->processParameters($testResult->id, $parameters);

            DB::commit();

            Log::channel('migrate-log')->info('ODB report processed successfully', [
                'ref_id' => $report['ref_id'],
                'test_result_id' => $testResult->id
            ]);

            Log::channel('migrate-log')->info('=== Report processing completed ===', [
                'ref_id' => $refId,
                'test_result_id' => $testResult->id,
                'total_parameters' => $totalParams
            ]);

            return $testResult;
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Find or create patient from ODB data
     */
    protected function findOrCreatePatient($report)
    {
        $icno = $report['ic'] ?? null;
        $name = $report['name'] ?? null;
        $gender = $report['gender'] ?? null;
        $dob = $report['dob'] ?? null;

        $patientInfo = checkIcno($icno);

        $patient = Patient::firstOrCreate(
            ['icno' => $icno],
            [
                'name' => $name,
                'gender' => $gender,
                'dob' => $dob,
                'age' => $patientInfo['age'],
                'ic_type' => $patientInfo['type'],
            ]
        );

        return $patient->id;
    }

    /**
     * Find or create doctor from ODB data
     */
    protected function findOrCreateDoctor($report)
    {
        $doctorName = $report['dr_name'] ?? null;
        $clinicName = $report['clinic_name'] ?? null;

        if (!$doctorName) {
            throw new RuntimeException('Doctor name is required');
        }

        $doctor = Doctor::firstOrCreate(
            [
                'lab_id' => self::LAB_ID,
                'name' => $doctorName,
            ],
            [
                'type' => 'CLINIC',
                'outlet_name' => $clinicName,
            ]
        );

        return $doctor->id;
    }

    /**
     * Create or update test result
     */
    protected function createTestResult($report, $patientId, $doctorId)
    {
        $refId = 'INN' . $report['ref_id'] ?? null;
        $labNo = $report['lab_no'] ?? null;

        $collectedOn = $report['collected_on'] ?? null; //collected_date
        $register_date = $report['register_date'] ?? null; //received_date
        $validatedDate = $report['validated_date'] ?? null; //reported_date

        $test_panel = $report['test_panel'] ?? null;
        $validated_by = $report['validated_by'] ?? null;
        $registered_by = $report['registered_by'] ?? null;
        $sampling_date = sanitizeDate($report['sampling_date'] ?? null);
        $exam_date = sanitizeDate($report['exam_date'] ?? null);
        $received_date = sanitizeDate($report['received_date'] ?? null);

        $overall_notes = $report['overall_notes'] ?? null;

        if (!$labNo) {
            throw new RuntimeException('Lab number is required');
        }

        $testResult = TestResult::updateOrCreate(
            [
                'lab_no' => $labNo,
                'ref_id' => $refId
            ],
            [
                'doctor_id' => $doctorId,
                'patient_id' => $patientId,
                'collected_date' => $collectedOn,
                'received_date' => $register_date,
                'reported_date' => $validatedDate,
                'validated_by' => $validated_by,
                'is_completed' => true,
            ]
        );

        //Extra attributes of a specific lab
        ReportRecord::firstOrCreate(
            [
                'test_result_id' => $testResult->id,
            ],
            [
                'test_panel' => $test_panel,
                'registered_by' => $registered_by,
                'sampling_exam' => $sampling_date,
                'exam_date' => $exam_date,
                'received_date' => $received_date,
                'overall_notes' => $overall_notes,
            ]
        );

        return $testResult;
    }

    /**
     * Process parameters hierarchically with smart detection
     */
    protected function processParameters($testResultId, $parameters)
    {
        $currentPanelCategoryId = null;
        $currentPanel = null;
        $paramCount = count($parameters);

        $counts = ['package' => 0, 'category' => 0, 'panel' => 0, 'item' => 0, 'skipped' => 0];

        Log::channel('migrate-log')->info('Processing parameters', [
            'test_result_id' => $testResultId,
            'total_count' => $paramCount
        ]);

        for ($i = 0; $i < $paramCount; $i++) {
            $param = $parameters[$i];
            $orderType = $param['order_type'];
            $orderId = $param['order_id'] ?? 'unknown';
            $nextParam = $i + 1 < $paramCount ? $parameters[$i + 1] : null;

            // Log::channel('migrate-log')->debug("Processing parameter #{$i}", [
            //     'order_id' => $orderId,
            //     'order_type' => $orderType,
            //     'index' => $i
            // ]);

            if ($orderType == 1) {
                // Package
                $this->processPackage($testResultId, $param);
                $counts['package']++;
            } elseif ($orderType == 2) {
                // Check if next parameter is also order_type=2
                if ($nextParam && $nextParam['order_type'] == 2) {
                    // Current is Panel Category
                    $currentPanelCategoryId = $this->processPanelCategory($param);
                    $counts['category']++;
                } else {
                    // Current is Panel
                    $currentPanel = $this->processPanel($param, $currentPanelCategoryId);
                    $counts['panel']++;
                }
            } elseif ($orderType == 3) {
                // Test Item - link to current panel
                if ($currentPanel) {
                    $this->processTestItem($testResultId, $param, $currentPanel);
                    $counts['item']++;
                } else {
                    Log::channel('migrate-log')->warning('Skipped test item - no current panel', [
                        'order_id' => $orderId,
                        'index' => $i
                    ]);
                    $counts['skipped']++;
                }
            }
        }

        Log::channel('migrate-log')->info('Parameters processing completed', [
            'test_result_id' => $testResultId,
            'total_processed' => $paramCount,
            'packages' => $counts['package'],
            'categories' => $counts['category'],
            'panels' => $counts['panel'],
            'items' => $counts['item'],
            'skipped' => $counts['skipped']
        ]);
    }

    /**
     * Process package (order_type = 1)
     * Package = Panel Profile (linked to test result)
     */
    protected function processPackage($testResultId, $package)
    {
        $packageName = $package['package_name'] ?? null;

        if (!$packageName) {
            return null;
        }

        $labId = self::LAB_ID;
        $code = generate_lab_code($packageName);

        $panelProfile = PanelProfile::firstOrCreate(
            [
                'lab_id' => $labId,
                'code' => $code,
            ],
            [
                'name' => $packageName,
            ]
        );

        TestResultProfile::firstOrCreate([
            'test_result_id' => $testResultId,
            'panel_profile_id' => $panelProfile->id
        ]);

        return $panelProfile->id;
    }

    /**
     * Process panel category (first order_type = 2 when followed by another)
     */
    protected function processPanelCategory($param)
    {
        $panelCategoryName = $param['panel_name'] ?? null;

        if (!$panelCategoryName) {
            return null;
        }

        $labId = self::LAB_ID;

        $panelCategory = PanelCategory::firstOrCreate(
            [
                'lab_id' => $labId,
                'name' => $panelCategoryName,
            ]
        );

        return $panelCategory->id;
    }

    /**
     * Process panel (order_type = 2)
     * Links panel to category if provided
     */
    protected function processPanel($panelData, $panelCategoryId = null)
    {
        $panelName = $panelData['panel_name'] ?? null;
        $orderId = $panelData['order_id'] ?? 'unknown';

        if (!$panelName) {
            Log::channel('migrate-log')->warning('Skipped panel - no panel_name', [
                'order_id' => $orderId
            ]);
            return null;
        }

        $labId = self::LAB_ID;
        $code = generate_lab_code($panelName);

        // Create master panel
        $masterPanel = MasterPanel::firstOrCreate(['name' => $panelName]);

        // Create panel with code from name
        $panel = Panel::firstOrCreate(
            [
                'lab_id' => $labId,
                'master_panel_id' => $masterPanel->id,
                'code' => $code,
            ],
            [
                'name' => $panelName,
                'panel_category_id' => $panelCategoryId,
            ]
        );

        // Log::channel('migrate-log')->debug('Panel created/found', [
        //     'order_id' => $orderId,
        //     'panel_id' => $panel->id,
        //     'panel_name' => $panelName
        // ]);

        return $panel;
    }

    /**
     * Process test item (order_type = 3)
     * Links test item to the current panel
     */
    protected function processTestItem($testResultId, $item, $panel)
    {
        $itemName = $item['panel_item']['name'] ?? null;
        $chineseName = $item['panel_item']['chinese_name'] ?? null;
        $unit = $item['panel_item']['unit'] ?? null;
        $resultValue = $item['result_value'] ?? null;
        $resultFlag = $item['result_flag'] ?? null;
        $refRange = $item['ref_range'] ?? null;
        $rangeDesc = $item['range_desc'] ?? null;
        $seq = $item['seq'] ?? 0;
        $orderId = $item['order_id'] ?? 'unknown';

        if (!$itemName || !$panel) {
            Log::channel('migrate-log')->warning('Skipped test item', [
                'order_id' => $orderId,
                'reason' => !$itemName ? 'no item name' : 'no panel',
                'has_panel' => $panel ? true : false
            ]);
            return;
        }

        $labId = self::LAB_ID;

        // Chinese name from JSON is already properly decoded to UTF-8
        $chiCharacter = $chineseName;

        // Clean unit field - unescape forward slashes
        $unit = cleanJsonString($unit);

        // Create master panel item
        $masterPanelItem = MasterPanelItem::firstOrCreate(
            ['name' => $itemName],
            [
                'unit' => $unit,
                'chi_character' => $chiCharacter,
            ]
        );

        $code = generate_lab_code($itemName);

        // Create panel item
        $panelItem = PanelItem::firstOrCreate(
            [
                'lab_id' => $labId,
                'master_panel_item_id' => $masterPanelItem->id,
                'identifier' => 'ODB_' . $item['order_id'],
            ],
            [
                'name' => $itemName,
                'code' => $code,
                'unit' => $unit,
            ]
        );

        // Link panel item to panel
        $panel->panelItems()->syncWithoutDetaching([$panelItem->id]);

        // Get panel_panel_item_id
        $panelPanelItem = DB::table('panel_panel_items')
            ->where('panel_id', $panel->id)
            ->where('panel_item_id', $panelItem->id)
            ->first();

        if ($panelPanelItem) {
            // Create reference range if exists
            $refRangeId = null;
            if ($refRange) {
                $referenceRange = ReferenceRange::firstOrCreate(
                    [
                        'value' => $refRange,
                        'panel_panel_item_id' => $panelPanelItem->id,
                    ]
                );
                $refRangeId = $referenceRange->id;
            }

            // Check for existing item to determine has_amended
            $existingItem = TestResultItem::where('test_result_id', $testResultId)
                ->where('panel_panel_item_id', $panelPanelItem->id)
                ->first();

            $hasAmended = false;
            if ($existingItem) {
                $normalizedExisting = $existingItem->value === '' ? null : $existingItem->value;
                $normalizedNew = $resultValue === '' ? null : $resultValue;
                $hasAmended = $normalizedExisting !== $normalizedNew;
            }

            // Create test result item
            $testResultItem = TestResultItem::updateOrCreate(
                [
                    'test_result_id' => $testResultId,
                    'panel_panel_item_id' => $panelPanelItem->id,
                ],
                [
                    'reference_range_id' => $refRangeId,
                    'value' => $resultValue,
                    'flag' => $resultFlag,
                    'sequence' => $seq,
                    'has_amended' => $hasAmended,
                ]
            );

            // Create comment hierarchy if range_desc exists
            if ($rangeDesc) {
                // Step 1: Create MasterPanelComment with range_desc text
                $masterPanelComment = MasterPanelComment::firstOrCreate([
                    'comment' => $rangeDesc
                ]);

                // Step 2: Create PanelComment linking panel + master comment
                $panelComment = PanelComment::firstOrCreate([
                    'panel_id' => $panel->id,
                    'master_panel_comment_id' => $masterPanelComment->id,
                ]);

                // Step 3: Create TestResultComment linking test result item + panel comment
                TestResultComment::firstOrCreate([
                    'test_result_item_id' => $testResultItem->id,
                    'panel_comment_id' => $panelComment->id,
                ]);
            }

            // Log::channel('migrate-log')->debug('Test item created successfully', [
            //     'order_id' => $orderId,
            //     'item_name' => $itemName,
            //     'test_result_item_id' => $testResultItem->id,
            //     'panel_id' => $panel->id,
            //     'has_ref_range' => $refRange ? true : false,
            //     'has_range_desc' => $rangeDesc ? true : false
            // ]);
        }
    }
}
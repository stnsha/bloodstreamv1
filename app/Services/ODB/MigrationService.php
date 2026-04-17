<?php

namespace App\Services\ODB;

use App\Models\Doctor;
use App\Models\Eurofins\ReportRecord;
use App\Models\Lab;
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
     * In-memory caches to reduce database queries
     */
    protected $masterPanelItemCache = [];
    protected $panelItemCache = [];
    protected $pivotIdCache = [];
    protected $masterCommentCache = [];
    protected $refRangeCache = [];
    protected $existingTestItems = [];

    /**
     * Process a single report with its parameters (split into smaller transactions)
     */
    public function processReport($report, $parameters)
    {
        $refId = $report['ref_id'] ?? 'unknown';
        $totalParams = count($parameters);
        $startTime = microtime(true);

        Log::channel('migrate-log')->info('Processing report', [
            'ref_id' => $refId,
            'param_count' => $totalParams
        ]);

        try {
            // Transaction 1: Create core entities (fast, minimal lock time)
            $coreData = DB::transaction(function () use ($report) {
                $patientId = $this->findOrCreatePatient($report);
                $doctorId = $this->findOrCreateDoctor($report);
                $testResult = $this->createTestResult($report, $patientId, $doctorId);

                return [
                    'test_result_id' => $testResult->id,
                    'patient_id' => $patientId,
                    'doctor_id' => $doctorId
                ];
            });

            // Clear cache after core creation
            $this->clearCaches();

            // Transaction 2: Process parameters (can be slow)
            DB::transaction(function () use ($parameters, $coreData) {
                $this->processParameters($coreData['test_result_id'], $parameters);
            });

            // Clear cache after parameter processing
            $this->clearCaches();

            // Force garbage collection
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            $duration = round(microtime(true) - $startTime, 2);

            Log::channel('migrate-log')->info('Report processed successfully', [
                'ref_id' => $refId,
                'test_result_id' => $coreData['test_result_id'],
                'duration_seconds' => $duration
            ]);

            return TestResult::find($coreData['test_result_id']);

        } catch (Throwable $e) {
            Log::channel('migrate-log')->error('Report processing failed', [
                'ref_id' => $refId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            throw $e;
        }
    }

    /**
     * Clear all in-memory caches
     *
     * @return void
     */
    protected function clearCaches()
    {
        $this->masterPanelItemCache = [];
        $this->panelItemCache = [];
        $this->pivotIdCache = [];
        $this->referenceRangeCache = [];
        $this->masterCommentCache = [];
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
        // Get lab code from Lab model
        $lab = Lab::find(self::LAB_ID);
        $labCode = $lab ? $lab->code : 'DUM';

        $refId = isset($report['ref_id']) ? $labCode . $report['ref_id'] : null;
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

        // Pre-fetch existing test result items to check for amendments (optimization)
        $this->existingTestItems = TestResultItem::where('test_result_id', $testResultId)
            ->get()
            ->keyBy('panel_panel_item_id')
            ->toArray();

        Log::channel('migrate-log')->info('Processing parameters', [
            'test_result_id' => $testResultId,
            'total_count' => $paramCount,
            'existing_items' => count($this->existingTestItems)
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

        // Create master panel item (with caching)
        $cacheKey = $itemName . '|' . $unit;
        if (!isset($this->masterPanelItemCache[$cacheKey])) {
            $this->masterPanelItemCache[$cacheKey] = MasterPanelItem::firstOrCreate(
                ['name' => $itemName],
                [
                    'unit' => $unit,
                    'chi_character' => $chiCharacter,
                ]
            );
        }
        $masterPanelItem = $this->masterPanelItemCache[$cacheKey];

        $code = generate_lab_code($itemName);

        // Create panel item (with caching)
        $identifier = 'ODB_' . $item['order_id'];
        if (!isset($this->panelItemCache[$identifier])) {
            $this->panelItemCache[$identifier] = PanelItem::firstOrCreate(
                [
                    'lab_id' => $labId,
                    'master_panel_item_id' => $masterPanelItem->id,
                    'identifier' => $identifier,
                ],
                [
                    'name' => $itemName,
                    'code' => $code,
                    'unit' => $unit,
                    'chi_character' => $chiCharacter,
                ]
            );
        }
        $panelItem = $this->panelItemCache[$identifier];

        // Link panel item to panel
        $panel->panelItems()->syncWithoutDetaching([$panelItem->id]);

        // Get panel_panel_item_id (with caching to avoid repeated DB queries)
        $pivotCacheKey = $panel->id . '_' . $panelItem->id;
        if (!isset($this->pivotIdCache[$pivotCacheKey])) {
            $panelPanelItem = DB::table('panel_panel_items')
                ->where('panel_id', $panel->id)
                ->where('panel_item_id', $panelItem->id)
                ->first();
            $this->pivotIdCache[$pivotCacheKey] = $panelPanelItem ? $panelPanelItem->id : null;
        }
        $panelPanelItemId = $this->pivotIdCache[$pivotCacheKey];

        if ($panelPanelItemId) {
            // Create reference range if exists (with caching)
            $refRangeId = null;
            if ($refRange) {
                $refCacheKey = $refRange . '_' . $panelPanelItemId;
                if (!isset($this->refRangeCache[$refCacheKey])) {
                    $this->refRangeCache[$refCacheKey] = ReferenceRange::firstOrCreate(
                        [
                            'value' => $refRange,
                            'panel_panel_item_id' => $panelPanelItemId,
                        ]
                    );
                }
                $refRangeId = $this->refRangeCache[$refCacheKey]->id;
            }

            // Check for existing item to determine has_amended (using pre-fetched data)
            $hasAmended = false;
            if (isset($this->existingTestItems[$panelPanelItemId])) {
                $existingItem = $this->existingTestItems[$panelPanelItemId];
                $normalizedExisting = ($existingItem['value'] ?? '') === '' ? null : $existingItem['value'];
                $normalizedNew = $resultValue === '' ? null : $resultValue;
                $hasAmended = $normalizedExisting !== $normalizedNew;
            }

            // Create test result item
            $testResultItem = TestResultItem::updateOrCreate(
                [
                    'test_result_id' => $testResultId,
                    'panel_panel_item_id' => $panelPanelItemId,
                ],
                [
                    'reference_range_id' => $refRangeId,
                    'value' => $resultValue,
                    'flag' => $resultFlag,
                    'sequence' => $seq,
                    'has_amended' => $hasAmended,
                ]
            );

            // Create comment hierarchy if range_desc exists (with caching)
            if ($rangeDesc) {
                // Step 1: Create MasterPanelComment with caching
                if (!isset($this->masterCommentCache[$rangeDesc])) {
                    $this->masterCommentCache[$rangeDesc] = MasterPanelComment::firstOrCreate([
                        'comment' => $rangeDesc
                    ]);
                }
                $masterPanelComment = $this->masterCommentCache[$rangeDesc];

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
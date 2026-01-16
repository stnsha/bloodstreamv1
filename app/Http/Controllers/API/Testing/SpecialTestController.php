<?php

namespace App\Http\Controllers\API\Testing;

use App\Constants\Innoquest\PanelPanelItem;
use App\Http\Controllers\Controller;
use App\Models\TestResult;
use App\Services\MyHealthService;
use App\Services\PanelInterpretationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SpecialTestController extends Controller
{
    protected $panelInterpretationService;
    protected $myHealthService;

    public function __construct(PanelInterpretationService $panelInterpretationService, MyHealthService $myHealthService)
    {
        $this->panelInterpretationService = $panelInterpretationService;
        $this->myHealthService = $myHealthService;
    }

    private function extractUpperLimit(?string $rangeValue): ?float
    {
        if (is_null($rangeValue)) {
            return null;
        }

        $range = trim($rangeValue);

        // Case: "< 41" -> extract 41
        if (str_starts_with($range, '<')) {
            return (float) trim(str_replace('<', '', $range));
        }

        // Case: "12-36" -> extract 36 (highest)
        if (str_contains($range, '-')) {
            $parts = explode('-', $range);
            return (float) trim(end($parts));
        }

        // Case: single numeric value
        if (is_numeric($range)) {
            return (float) $range;
        }

        return null;
    }

    public function index()
    {
        // Get result from id: 24477
        $testResult = TestResult::with(['patient'])->find(24477);

        // 1. Get result items
        $testResultItems = $testResult->testResultItems()
            ->whereIn('panel_panel_item_id', PanelPanelItem::PANEL_PANEL_ITEM_IDS)
            ->get()
            ->keyBy('panel_panel_item_id');

        // 2. Calculate interpretations
        // 2.1 CRI-I, CRI-II, AIP
        $lr = $this->panelInterpretationService->lipidInterpretation(
            cri_i: $testResultItems[PanelPanelItem::CRI_I]->value ?? null,
            cri_ii: $testResultItems[PanelPanelItem::CRI_II]->value ?? null,
            aip: $testResultItems[PanelPanelItem::AIP]->value ?? null,
        );

        // 2.2 Atherogenic Coefficient
        $ac = $this->panelInterpretationService->calculateAC(
            totalCholesterol: $testResultItems[PanelPanelItem::TOTAL_CHOLESTEROL]->value ?? null,
            hdlCholesterol: $testResultItems[PanelPanelItem::HDL]->value ?? null,
        );

        // 2.3 FIB-4 Index
        $age = $testResult->patient->age ?? ($testResult->patient->dob ? Carbon::parse($testResult->patient->dob)->age : null);
        $fib = $this->panelInterpretationService->calculateFIB(
            age: $age,
            ast: $testResultItems[PanelPanelItem::AST]->value ?? null,
            alt: $testResultItems[PanelPanelItem::ALT]->value ?? null,
            plateletCount: $this->getPlateletsValue($testResultItems),
        );

        // 2.4 APRI
        $astUpperLimit = null;
        $astItem = $testResultItems[PanelPanelItem::AST] ?? null;
 
        if ($astItem && $astItem->reference_range_id) {
            $referenceRange = $astItem->referenceRange;
            if ($referenceRange) {
                $astUpperLimit = $this->extractUpperLimit($referenceRange->value);
            } else {
                $astUpperLimit = null;
            }
        }

        $ap = $this->panelInterpretationService->calculateAPRI(
            ast: $testResultItems[PanelPanelItem::AST]->value ?? null,
            astRef: $astUpperLimit,
            plateletCount: $this->getPlateletsValue($testResultItems),
        );

        // 2.5 NFS
        $fasting = $testResultItems[PanelPanelItem::GLUCOSE_FASTING_TYPE]->value == 'Fasting' ? true : false;
        $bmi = $this->myHealthService->getPatientBMI($testResult->patient->icno);

        $nfs = $this->panelInterpretationService->calculateNFS(
            age: $age,
            bmi: $bmi,
            fasting: $fasting,
            ast: $testResultItems[PanelPanelItem::AST]->value ?? null,
            alt: $testResultItems[PanelPanelItem::ALT]->value ?? null,
            plateletCount: $this->getPlateletsValue($testResultItems),
            albumin: $testResultItems[PanelPanelItem::ALBUMIN]->value ?? null,
        );

        $data = [
            'cri_i' => [
                'panel_panel_item_id' => $lr['cri_i_panel_panel_item_id'],
                'value' => $testResultItems[PanelPanelItem::CRI_I]->value, //already has in test_result_items
                'panel_interpretation_id' => $lr['cri_i_interpretation'],
            ],
            'cri_ii' => [
                'panel_panel_item_id' => $lr['cri_ii_panel_panel_item_id'],
                'value' => $testResultItems[PanelPanelItem::CRI_II]->value, //already has in test_result_items
                'panel_interpretation_id' => $lr['cri_ii_interpretation'],
            ],
            'aip' => [
                'panel_panel_item_id' => $lr['aip_panel_panel_item_id'],
                'value' => $testResultItems[PanelPanelItem::AIP]->value, //already has in test_result_items
                'panel_interpretation_id' => $lr['aip_interpretation'],
            ],
            'ac' => [
                'panel_panel_item_id' => $ac['panel_panel_item_id'],
                'value' => $ac['value'],
                'panel_interpretation_id' => $ac['ac_interpretation'],
            ],
            'fib' => [
                'panel_panel_item_id' => $fib['panel_panel_item_id'],
                'value' => $fib['value'],
                'panel_interpretation_id' => $fib['fib_interpretation'],
            ],
            'apri' => [
                'panel_panel_item_id' => $ap['panel_panel_item_id'],
                'value' => $ap['value'],
                'panel_interpretation_id' => $ap['apri_interpretation'],
            ],
            'nfs' => [
                'panel_panel_item_id' => $nfs['panel_panel_item_id'],
                'value' => $nfs['value'],
                'panel_interpretation_id' => $nfs['nfs_interpretation'],
            ],
        ];

        foreach ($data as $item) {
            $testResult->testResultSpecialTests()->updateOrCreate(
                [
                    'test_result_id' => $testResult->id,
                    'panel_panel_item_id' => $item['panel_panel_item_id']
                ],
                [
                    'value' => $item['value'],
                    'panel_interpretation_id' => $item['panel_interpretation_id']
                ]
            );
        }
    }

    public function rerunTestResults()
    {
        // $testResultIds = [25473, 25501, 25502, 25504, 25505, 25506, 25507, 25508, 25509, 25510, 25511, 25512, 25513, 25514, 25515, 25516, 25517, 25518];
        $testResultIds = [25522];

        Log::info('Starting rerunTestResults for special test calculation', [
            'test_result_ids' => $testResultIds,
            'count' => count($testResultIds)
        ]);

        $testResults = TestResult::with(['patient'])->whereIn('id', $testResultIds)->get();

        $processed = 0;
        $failed = 0;

        foreach ($testResults as $testResult) {
            try {
                DB::beginTransaction();

                $this->calculateSpecialTests($testResult);

                DB::commit();
                $processed++;

                Log::info('Special tests calculated successfully', [
                    'test_result_id' => $testResult->id
                ]);
            } catch (Exception $e) {
                DB::rollBack();
                $failed++;

                Log::error('Failed to calculate special tests', [
                    'test_result_id' => $testResult->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Completed rerunTestResults', [
            'processed' => $processed,
            'failed' => $failed
        ]);

        return response()->json([
            'message' => 'Special tests recalculation completed',
            'processed' => $processed,
            'failed' => $failed
        ]);
    }

    /**
     * Check if all parameters required for special tests exist for a given test result.
     *
     * @param int $testResultId
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkParameter(int $testResultId)
    {
        Log::info('Starting checkParameter for special tests', [
            'test_result_id' => $testResultId
        ]);

        $testResult = TestResult::with(['patient'])->find($testResultId);

        if (!$testResult) {
            Log::warning('TestResult not found for checkParameter', [
                'test_result_id' => $testResultId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'TestResult not found',
                'test_result_id' => $testResultId
            ], 404);
        }

        $testResultItems = $testResult->testResultItems()
            ->whereIn('panel_panel_item_id', PanelPanelItem::PANEL_PANEL_ITEM_IDS)
            ->get()
            ->keyBy('panel_panel_item_id');

        // Check age
        $age = $testResult->patient->age ?? ($testResult->patient->dob ? Carbon::parse($testResult->patient->dob)->age : null);

        // Check BMI from MyHealth
        $bmi = null;
        if ($testResult->patient && $testResult->patient->icno) {
            $bmi = $this->myHealthService->getPatientBMI($testResult->patient->icno);
        }

        // Check AST reference range (upper limit)
        $astUpperLimit = null;
        $astItem = $testResultItems[PanelPanelItem::AST] ?? null;
        if ($astItem && $astItem->reference_range_id) {
            $referenceRange = $astItem->referenceRange;
            if ($referenceRange) {
                $astUpperLimit = $this->extractUpperLimit($referenceRange->value);
            }
        }

        // Build parameter status array
        $parameters = [
            // Test result item parameters
            'cri_i' => $this->getParameterStatus($testResultItems, PanelPanelItem::CRI_I),
            'cri_ii' => $this->getParameterStatus($testResultItems, PanelPanelItem::CRI_II),
            'aip' => $this->getParameterStatus($testResultItems, PanelPanelItem::AIP),
            'total_cholesterol' => $this->getParameterStatus($testResultItems, PanelPanelItem::TOTAL_CHOLESTEROL),
            'hdl' => $this->getParameterStatus($testResultItems, PanelPanelItem::HDL),
            'ast' => $this->getParameterStatus($testResultItems, PanelPanelItem::AST),
            'alt' => $this->getParameterStatus($testResultItems, PanelPanelItem::ALT),
            'platelets' => $this->getPlateletsParameterStatus($testResultItems),
            'albumin' => $this->getParameterStatus($testResultItems, PanelPanelItem::ALBUMIN),
            'glucose_fasting_type' => $this->getParameterStatus($testResultItems, PanelPanelItem::GLUCOSE_FASTING_TYPE),

            // External parameters
            'age' => $age !== null ? 'exist' : 'null',
            'bmi' => $bmi !== null ? 'exist' : 'null',
            'ast_upper_limit' => $astUpperLimit !== null ? 'exist' : 'null',
        ];

        // Calculate which special tests can be computed
        $canCompute = [
            'lipid_interpretation' => $parameters['cri_i'] === 'exist' || $parameters['cri_ii'] === 'exist' || $parameters['aip'] === 'exist',
            'atherogenic_coefficient' => $parameters['total_cholesterol'] === 'exist' && $parameters['hdl'] === 'exist',
            'fib4_index' => $parameters['age'] === 'exist' && $parameters['ast'] === 'exist' && $parameters['alt'] === 'exist' && $parameters['platelets'] === 'exist',
            'apri' => $parameters['ast'] === 'exist' && $parameters['ast_upper_limit'] === 'exist' && $parameters['platelets'] === 'exist',
            'nfs' => $parameters['age'] === 'exist' && $parameters['bmi'] === 'exist' && $parameters['ast'] === 'exist' && $parameters['alt'] === 'exist' && $parameters['platelets'] === 'exist' && $parameters['albumin'] === 'exist',
        ];

        Log::info('Completed checkParameter for special tests', [
            'test_result_id' => $testResultId,
            'parameters' => $parameters,
            'can_compute' => $canCompute
        ]);

        return response()->json([
            'success' => true,
            'test_result_id' => $testResultId,
            'parameters' => $parameters,
            'can_compute' => $canCompute,
        ]);
    }

    /**
     * Get the status of a parameter from test result items.
     *
     * @param \Illuminate\Support\Collection $testResultItems
     * @param int $panelPanelItemId
     * @return string
     */
    private function getParameterStatus($testResultItems, int $panelPanelItemId): string
    {
        $item = $testResultItems[$panelPanelItemId] ?? null;

        if ($item === null) {
            return 'null';
        }

        if ($item->value === null || $item->value === '') {
            return 'null';
        }

        return 'exist';
    }

    /**
     * Get platelets value with fallback from primary (61) to alternate (166).
     *
     * @param \Illuminate\Support\Collection $testResultItems
     * @return string|null
     */
    private function getPlateletsValue($testResultItems): ?string
    {
        $item = $testResultItems[PanelPanelItem::PLATELETS] ?? null;
        if ($item !== null && $item->value !== null && $item->value !== '') {
            return $item->value;
        }

        $altItem = $testResultItems[PanelPanelItem::PLATELETS_ALT] ?? null;
        if ($altItem !== null && $altItem->value !== null && $altItem->value !== '') {
            return $altItem->value;
        }

        return null;
    }

    /**
     * Get platelets parameter status checking both primary (61) and alternate (166) IDs.
     *
     * @param \Illuminate\Support\Collection $testResultItems
     * @return string
     */
    private function getPlateletsParameterStatus($testResultItems): string
    {
        $primaryStatus = $this->getParameterStatus($testResultItems, PanelPanelItem::PLATELETS);
        if ($primaryStatus === 'exist') {
            return 'exist';
        }

        return $this->getParameterStatus($testResultItems, PanelPanelItem::PLATELETS_ALT);
    }

    protected function calculateSpecialTests(TestResult $testResult): void
    {
        $testResult->load('patient');

        $testResultItems = $testResult->testResultItems()
            ->whereIn('panel_panel_item_id', PanelPanelItem::PANEL_PANEL_ITEM_IDS)
            ->get()
            ->keyBy('panel_panel_item_id');

        // 1. Lipid Interpretation (CRI-I, CRI-II, AIP)
        $lr = $this->panelInterpretationService->lipidInterpretation(
            cri_i: $testResultItems[PanelPanelItem::CRI_I]->value ?? null,
            cri_ii: $testResultItems[PanelPanelItem::CRI_II]->value ?? null,
            aip: $testResultItems[PanelPanelItem::AIP]->value ?? null,
        );

        // 2. Atherogenic Coefficient
        $ac = $this->panelInterpretationService->calculateAC(
            totalCholesterol: $testResultItems[PanelPanelItem::TOTAL_CHOLESTEROL]->value ?? null,
            hdlCholesterol: $testResultItems[PanelPanelItem::HDL]->value ?? null,
        );

        // 3. FIB-4 Index
        $age = $testResult->patient->age ?? ($testResult->patient->dob ? Carbon::parse($testResult->patient->dob)->age : null);
        $fib = $this->panelInterpretationService->calculateFIB(
            age: $age,
            ast: $testResultItems[PanelPanelItem::AST]->value ?? null,
            alt: $testResultItems[PanelPanelItem::ALT]->value ?? null,
            plateletCount: $this->getPlateletsValue($testResultItems),
        );

        // 4. APRI - requires AST upper limit from reference range
        $astUpperLimit = null;
        $astItem = $testResultItems[PanelPanelItem::AST] ?? null;

        if ($astItem && $astItem->reference_range_id) {
            $referenceRange = $astItem->referenceRange;
            if ($referenceRange) {
                $astUpperLimit = $this->extractUpperLimit($referenceRange->value);
            }
        }

        $ap = $this->panelInterpretationService->calculateAPRI(
            ast: $testResultItems[PanelPanelItem::AST]->value ?? null,
            astRef: $astUpperLimit,
            plateletCount: $this->getPlateletsValue($testResultItems),
        );

        // 5. NFS - requires BMI from MyHealth
        $glucoseFastingItem = $testResultItems[PanelPanelItem::GLUCOSE_FASTING_TYPE] ?? null;
        $fasting = $glucoseFastingItem && $glucoseFastingItem->value == 'Fasting';
        $bmi = $this->myHealthService->getPatientBMI($testResult->patient->icno);

        $nfs = $this->panelInterpretationService->calculateNFS(
            age: $age,
            bmi: $bmi,
            fasting: $fasting,
            ast: $testResultItems[PanelPanelItem::AST]->value ?? null,
            alt: $testResultItems[PanelPanelItem::ALT]->value ?? null,
            plateletCount: $this->getPlateletsValue($testResultItems),
            albumin: $testResultItems[PanelPanelItem::ALBUMIN]->value ?? null,
        );

        // Compile data for saving
        $data = [
            'cri_i' => [
                'panel_panel_item_id' => $lr['cri_i_panel_panel_item_id'],
                'value' => $testResultItems[PanelPanelItem::CRI_I]->value ?? null,
                'panel_interpretation_id' => $lr['cri_i_interpretation'],
            ],
            'cri_ii' => [
                'panel_panel_item_id' => $lr['cri_ii_panel_panel_item_id'],
                'value' => $testResultItems[PanelPanelItem::CRI_II]->value ?? null,
                'panel_interpretation_id' => $lr['cri_ii_interpretation'],
            ],
            'aip' => [
                'panel_panel_item_id' => $lr['aip_panel_panel_item_id'],
                'value' => $testResultItems[PanelPanelItem::AIP]->value ?? null,
                'panel_interpretation_id' => $lr['aip_interpretation'],
            ],
            'ac' => [
                'panel_panel_item_id' => $ac['panel_panel_item_id'],
                'value' => $ac['value'],
                'panel_interpretation_id' => $ac['ac_interpretation'],
            ],
            'fib' => [
                'panel_panel_item_id' => $fib['panel_panel_item_id'],
                'value' => $fib['value'],
                'panel_interpretation_id' => $fib['fib_interpretation'],
            ],
            'apri' => [
                'panel_panel_item_id' => $ap['panel_panel_item_id'],
                'value' => $ap['value'],
                'panel_interpretation_id' => $ap['apri_interpretation'],
            ],
            'nfs' => [
                'panel_panel_item_id' => $nfs['panel_panel_item_id'],
                'value' => $nfs['value'],
                'panel_interpretation_id' => $nfs['nfs_interpretation'],
            ],
        ];

        // Save special tests
        foreach ($data as $key => $item) {
            if ($item['panel_panel_item_id']) {
                $testResult->testResultSpecialTests()->updateOrCreate(
                    [
                        'test_result_id' => $testResult->id,
                        'panel_panel_item_id' => $item['panel_panel_item_id']
                    ],
                    [
                        'value' => $item['value'],
                        'panel_interpretation_id' => $item['panel_interpretation_id']
                    ]
                );
            }
        }
    }
}

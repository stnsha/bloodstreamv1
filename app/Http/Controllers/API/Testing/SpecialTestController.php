<?php

namespace App\Http\Controllers\API\Testing;

use App\Constants\Innoquest\PanelPanelItem;
use App\Http\Controllers\Controller;
use App\Models\TestResult;
use App\Services\MyHealthService;
use App\Services\PanelInterpretationService;
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
        $age = $testResult->patient->age;
        $fib = $this->panelInterpretationService->calculateFIB(
            age: $age,
            ast: $testResultItems[PanelPanelItem::AST]->value ?? null,
            alt: $testResultItems[PanelPanelItem::ALT]->value ?? null,
            plateletCount: $testResultItems[PanelPanelItem::PLATELETS]->value ?? null,
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
            plateletCount: $testResultItems[PanelPanelItem::PLATELETS]->value ?? null,
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
            plateletCount: $testResultItems[PanelPanelItem::PLATELETS]->value ?? null,
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
        $testResultIds = [25473, 25501, 25502, 25504, 25505, 25506, 25507, 25508, 25509, 25510, 25511, 25512, 25513, 25514, 25515, 25516, 25517, 25518];

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
        $age = $testResult->patient->age;
        $fib = $this->panelInterpretationService->calculateFIB(
            age: $age,
            ast: $testResultItems[PanelPanelItem::AST]->value ?? null,
            alt: $testResultItems[PanelPanelItem::ALT]->value ?? null,
            plateletCount: $testResultItems[PanelPanelItem::PLATELETS]->value ?? null,
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
            plateletCount: $testResultItems[PanelPanelItem::PLATELETS]->value ?? null,
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
            plateletCount: $testResultItems[PanelPanelItem::PLATELETS]->value ?? null,
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

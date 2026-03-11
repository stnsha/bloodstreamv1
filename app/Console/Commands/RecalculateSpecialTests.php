<?php

namespace App\Console\Commands;

use App\Constants\Innoquest\PanelPanelItem as PanelPanelItemConstants;
use App\Models\TestResult;
use App\Services\MyHealthService;
use App\Services\PanelInterpretationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalculateSpecialTests extends Command
{
    // Usage examples:
    //   By IDs:         php artisan special-tests:recalculate 31485,31486
    //   By date range:  php artisan special-tests:recalculate --from=2026-03-01 --to=2026-03-11
    //                   (only picks up test results with panel_profile_id = 8)
    protected $signature = 'special-tests:recalculate
                            {ids? : Comma-separated test_result_id values (e.g. 100,101,102)}
                            {--from= : Start date for date range mode (Y-m-d), requires --to}
                            {--to= : End date for date range mode (Y-m-d), requires --from}';

    protected $description = 'Recalculate special tests (CRI-I, CRI-II, AIP, AC, FIB-4, APRI, NFS) for given test result IDs or a date range (panel_profile_id = 8 required)';

    public function handle(): int
    {
        $rawIds = $this->argument('ids');
        $from = $this->option('from');
        $to = $this->option('to');

        // Validate: must provide either ids or both --from and --to
        if (!$rawIds && (!$from || !$to)) {
            $this->error('Provide either ids argument or both --from and --to options.');
            return Command::FAILURE;
        }

        if (($from && !$to) || (!$from && $to)) {
            $this->error('Both --from and --to must be provided together.');
            return Command::FAILURE;
        }

        $panelInterpretationService = app(PanelInterpretationService::class);
        $myHealthService = app(MyHealthService::class);

        if ($from && $to) {
            $fromDate = Carbon::parse($from)->startOfDay();
            $toDate = Carbon::parse($to)->endOfDay();

            Log::info('RecalculateSpecialTests started (date range mode)', [
                'from' => $fromDate->toDateTimeString(),
                'to' => $toDate->toDateTimeString(),
                'panel_profile_id' => 8,
            ]);

            $this->info("Querying test results from {$fromDate->toDateString()} to {$toDate->toDateString()} with panel_profile_id = 8...");

            $testResults = TestResult::with(['patient'])
                ->whereHas('testResultProfiles', function ($q) {
                    $q->where('panel_profile_id', 8);
                })
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->get();

            $this->info("Found {$testResults->count()} record(s).");

            Log::info('RecalculateSpecialTests: records found', [
                'count' => $testResults->count(),
                'test_result_ids' => $testResults->pluck('id')->toArray(),
            ]);
        } else {
            $ids = array_filter(array_map('intval', explode(',', $rawIds)));

            if (empty($ids)) {
                $this->error('No valid test_result_id values provided.');
                return Command::FAILURE;
            }

            Log::info('RecalculateSpecialTests started (ids mode)', [
                'test_result_ids' => $ids,
                'count' => count($ids),
            ]);

            $this->info('Recalculating special tests for ' . count($ids) . ' record(s)...');

            $testResults = TestResult::with(['patient'])->whereIn('id', $ids)->get();

            $notFound = array_diff($ids, $testResults->pluck('id')->toArray());
            foreach ($notFound as $missingId) {
                $this->warn("TestResult ID {$missingId} not found, skipping.");
                Log::warning('RecalculateSpecialTests: TestResult not found', ['test_result_id' => $missingId]);
            }
        }

        $processed = 0;
        $failed = 0;
        $rows = [];

        foreach ($testResults as $testResult) {
            Log::info('RecalculateSpecialTests: processing record', ['test_result_id' => $testResult->id]);

            try {
                DB::beginTransaction();

                $this->calculateSpecialTests($testResult, $panelInterpretationService, $myHealthService);

                DB::commit();

                $processed++;
                $rows[] = [$testResult->id, 'OK', '-'];

                Log::info('RecalculateSpecialTests: record completed', ['test_result_id' => $testResult->id]);
            } catch (Exception $e) {
                DB::rollBack();

                $failed++;
                $rows[] = [$testResult->id, 'FAILED', $e->getMessage()];

                Log::error('RecalculateSpecialTests: record failed', [
                    'test_result_id' => $testResult->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        $this->table(['ID', 'Status', 'Error'], $rows);
        $this->info("Done. Processed: {$processed}, Failed: {$failed}.");

        Log::info('RecalculateSpecialTests completed', [
            'processed' => $processed,
            'failed' => $failed,
        ]);

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function calculateSpecialTests(
        TestResult $testResult,
        PanelInterpretationService $panelInterpretationService,
        MyHealthService $myHealthService
    ): void {
        $testResult->load('patient');

        $testResultItems = $testResult->testResultItems()
            ->whereIn('panel_panel_item_id', PanelPanelItemConstants::PANEL_PANEL_ITEM_IDS)
            ->with('referenceRange')
            ->get()
            ->keyBy('panel_panel_item_id');

        // 1. Lipid Interpretation (CRI-I, CRI-II, AIP)
        $lr = $panelInterpretationService->lipidInterpretation(
            cri_i: $testResultItems[PanelPanelItemConstants::CRI_I]->value ?? null,
            cri_ii: $testResultItems[PanelPanelItemConstants::CRI_II]->value ?? null,
            aip: $testResultItems[PanelPanelItemConstants::AIP]->value ?? null,
        );

        // 2. Atherogenic Coefficient
        $ac = $panelInterpretationService->calculateAC(
            totalCholesterol: $testResultItems[PanelPanelItemConstants::TOTAL_CHOLESTEROL]->value ?? null,
            hdlCholesterol: $testResultItems[PanelPanelItemConstants::HDL]->value ?? null,
        );

        // 3. FIB-4 Index
        $age = $testResult->patient->age ?? ($testResult->patient->dob ? Carbon::parse($testResult->patient->dob)->age : null);
        $fib = $panelInterpretationService->calculateFIB(
            age: $age,
            ast: $testResultItems[PanelPanelItemConstants::AST]->value ?? null,
            alt: $testResultItems[PanelPanelItemConstants::ALT]->value ?? null,
            plateletCount: $this->getPlateletsValue($testResultItems),
        );

        // 4. APRI - requires AST upper limit from reference range
        $astUpperLimit = null;
        $astItem = $testResultItems[PanelPanelItemConstants::AST] ?? null;
        if ($astItem && $astItem->reference_range_id) {
            $referenceRange = $astItem->referenceRange;
            if ($referenceRange) {
                $astUpperLimit = extractUpperLimit($referenceRange->value);
            }
        }

        $ap = $panelInterpretationService->calculateAPRI(
            ast: $testResultItems[PanelPanelItemConstants::AST]->value ?? null,
            astRef: $astUpperLimit,
            plateletCount: $this->getPlateletsValue($testResultItems),
        );

        // 5. NFS - requires BMI from MyHealth
        $glucoseFastingItem = $testResultItems[PanelPanelItemConstants::GLUCOSE_FASTING_TYPE] ?? null;
        $fasting = $glucoseFastingItem && $glucoseFastingItem->value == 'Fasting';
        $bmi = $myHealthService->getPatientBMI($testResult->patient->icno);

        $nfs = $panelInterpretationService->calculateNFS(
            age: $age,
            bmi: $bmi,
            fasting: $fasting,
            ast: $testResultItems[PanelPanelItemConstants::AST]->value ?? null,
            alt: $testResultItems[PanelPanelItemConstants::ALT]->value ?? null,
            plateletCount: $this->getPlateletsValue($testResultItems),
            albumin: $testResultItems[PanelPanelItemConstants::ALBUMIN]->value ?? null,
        );

        $data = [
            'cri_i' => [
                'panel_panel_item_id' => $lr['cri_i_panel_panel_item_id'],
                'value' => $testResultItems[PanelPanelItemConstants::CRI_I]->value ?? null,
                'panel_interpretation_id' => $lr['cri_i_interpretation'],
            ],
            'cri_ii' => [
                'panel_panel_item_id' => $lr['cri_ii_panel_panel_item_id'],
                'value' => $testResultItems[PanelPanelItemConstants::CRI_II]->value ?? null,
                'panel_interpretation_id' => $lr['cri_ii_interpretation'],
            ],
            'aip' => [
                'panel_panel_item_id' => $lr['aip_panel_panel_item_id'],
                'value' => $testResultItems[PanelPanelItemConstants::AIP]->value ?? null,
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
            if ($item['panel_panel_item_id']) {
                $testResult->testResultSpecialTests()->updateOrCreate(
                    [
                        'test_result_id' => $testResult->id,
                        'panel_panel_item_id' => $item['panel_panel_item_id'],
                    ],
                    [
                        'value' => $item['value'],
                        'panel_interpretation_id' => $item['panel_interpretation_id'],
                    ]
                );
            }
        }
    }

    private function getPlateletsValue($testResultItems): ?string
    {
        $item = $testResultItems[PanelPanelItemConstants::PLATELETS] ?? null;
        if ($item !== null && $item->value !== null && $item->value !== '') {
            return $item->value;
        }

        $altItem = $testResultItems[PanelPanelItemConstants::PLATELETS_ALT] ?? null;
        if ($altItem !== null && $altItem->value !== null && $altItem->value !== '') {
            return $altItem->value;
        }

        return null;
    }
}

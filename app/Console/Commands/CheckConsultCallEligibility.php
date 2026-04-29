<?php

namespace App\Console\Commands;

use App\Constants\ConsultCall\PanelPanelItem;
use App\Models\ClinicalCondition;
use App\Models\ConsultCallDetails;
use App\Models\Patient;
use App\Models\TestResult;
use App\Services\ConditionEvaluatorService;
use App\Services\ConsultCallEligibilityService;
use App\Services\MyHealthService;
use App\Services\OctopusApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckConsultCallEligibility extends Command
{
    protected $signature = 'testing:check-consult-eligibility
        {--test_result_id= : The ID of the test result to check}
        {--dry-run : Show all diagnostic info without writing to the database}';

    protected $description = 'Check consult call eligibility for a single test result and show the reason if skipped';

    public function handle(): int
    {
        $id     = $this->option('test_result_id');
        $dryRun = (bool) $this->option('dry-run');

        if (! $id || ! is_numeric($id)) {
            $this->error('--test_result_id is required and must be a numeric value.');

            return self::FAILURE;
        }

        $id = (int) $id;

        Log::info('CheckConsultCallEligibility: Starting', ['test_result_id' => $id, 'dry_run' => $dryRun]);

        if ($dryRun) {
            $this->warn('DRY RUN MODE — no records will be written.');
        }

        $this->line("Checking test result ID: {$id}");
        $this->line('');

        // ── Load test result ────────────────────────────────────────────────
        $testResult = TestResult::find($id);

        if (! $testResult) {
            $this->error("Test result ID {$id} not found.");
            Log::info('CheckConsultCallEligibility: Test result not found', ['test_result_id' => $id]);

            return self::FAILURE;
        }

        $this->line('  is_completed  : ' . ($testResult->is_completed ? 'true' : 'false'));
        $this->line('  patient_id    : ' . ($testResult->patient_id ?? 'null'));
        $this->line('  ref_id        : ' . ($testResult->ref_id ?? 'null'));
        $this->line('  collected_date: ' . ($testResult->collected_date ?? 'null'));
        $this->line('');

        // ── Gate 1: is_completed ────────────────────────────────────────────
        if (! $testResult->is_completed) {
            $this->warn('SKIPPED: test result is not completed (is_completed = false)');
            Log::info('CheckConsultCallEligibility: Skipped - not completed', ['test_result_id' => $id]);

            return self::SUCCESS;
        }

        $this->line('  [PASS] is_completed = true');

        // ── Gate 2: patient_id ──────────────────────────────────────────────
        if (! $testResult->patient_id) {
            $this->warn('SKIPPED: no patient linked (patient_id is null)');
            Log::info('CheckConsultCallEligibility: Skipped - no patient', ['test_result_id' => $id]);

            return self::SUCCESS;
        }

        $this->line("  [PASS] patient_id = {$testResult->patient_id}");

        // ── Gate 3: ref_id ──────────────────────────────────────────────────
        if (! $testResult->ref_id) {
            $this->warn('SKIPPED: ref_id is null');
            Log::info('CheckConsultCallEligibility: Skipped - no ref_id', ['test_result_id' => $id]);

            return self::SUCCESS;
        }

        $this->line("  [PASS] ref_id = {$testResult->ref_id}");

        // ── Gate 4: outlet eligibility ──────────────────────────────────────
        $labCode    = $testResult->doctor->lab->code ?? null;
        $octopusApi = app(OctopusApiService::class);
        $customer   = $octopusApi->eligibleConsultCallByOutlet($testResult->ref_id, $labCode);

        if (! $customer) {
            $this->warn('SKIPPED: not an eligible outlet customer (ref_id=' . $testResult->ref_id . ', lab_code=' . ($labCode ?? 'null') . ')');
            Log::info('CheckConsultCallEligibility: Skipped - no eligible customer', [
                'test_result_id' => $id,
                'ref_id'         => $testResult->ref_id,
                'lab_code'       => $labCode,
            ]);

            return self::SUCCESS;
        }

        $customerId = (int) $customer['customer_id'];
        $outletId   = isset($customer['outlet_id']) ? (int) $customer['outlet_id'] : null;

        $this->line('  [PASS] Outlet check passed (customer_id=' . $customerId . ', outlet_id=' . ($outletId ?? 'null') . ')');

        // ── Gate 5: ConsultCallDetails already exists ───────────────────────
        $existedBefore = ConsultCallDetails::where('test_result_id', $id)->exists();

        if ($existedBefore) {
            $this->info('ALREADY EXISTS: a ConsultCallDetails record already exists for this test result.');
            Log::info('CheckConsultCallEligibility: Already exists', ['test_result_id' => $id]);

            return self::SUCCESS;
        }

        $this->line('  [PASS] No existing ConsultCallDetails record found.');
        $this->line('');

        // ── Deep diagnostic: panel items ────────────────────────────────────
        $this->line('--- Panel Item Check ---');

        $items        = $testResult->testResultItems()
            ->whereIn('panel_panel_item_id', PanelPanelItem::ALL_IDS)
            ->get();
        $foundIds     = $items->pluck('panel_panel_item_id')->toArray();
        $allCategoryOk = true;

        $this->line('  Items found with matching panel_panel_item_ids: ' . (count($foundIds) > 0 ? implode(', ', $foundIds) : 'none'));
        $this->line('');

        $extractedValues = [];

        foreach (PanelPanelItem::REQUIRED_CATEGORIES as $category => $ids) {
            $value     = null;
            $foundId   = null;

            foreach ($ids as $panelItemId) {
                $item = $items->firstWhere('panel_panel_item_id', $panelItemId);
                if ($item && $item->value !== null && $item->value !== '') {
                    $value   = (float) $item->value;
                    $foundId = $panelItemId;
                    break;
                }
            }

            $idList = implode(', ', $ids);

            if ($value !== null) {
                $this->line("  [PASS] {$category} (IDs: {$idList}) = {$value} (item #{$foundId})");
            } else {
                $hasAnyItem = ! empty(array_intersect($ids, $foundIds));

                if ($hasAnyItem) {
                    $this->warn("  [FAIL] {$category} (IDs: {$idList}) — item found but value is null or empty");
                } else {
                    $this->warn("  [FAIL] {$category} (IDs: {$idList}) — no matching item found in test result items");
                }

                $allCategoryOk = false;
            }

            $extractedValues[$category] = $value;
        }

        $this->line('');

        if (! $allCategoryOk) {
            $this->warn('SKIPPED: one or more required panel categories are missing or have no value.');
            $this->line('         The eligibility service requires all categories to proceed.');
            Log::info('CheckConsultCallEligibility: Skipped - incomplete panel categories', [
                'test_result_id' => $id,
                'found_ids'      => $foundIds,
            ]);

            return self::SUCCESS;
        }

        $this->line('  [PASS] All required panel categories present.');
        $this->line('');

        // ── Deep diagnostic: patient data ───────────────────────────────────
        $this->line('--- Patient & Clinical Data ---');

        $patient = Patient::find($testResult->patient_id);

        if (! $patient) {
            $this->warn('SKIPPED: patient record not found (patient_id=' . $testResult->patient_id . ')');
            Log::info('CheckConsultCallEligibility: Skipped - patient not found', ['test_result_id' => $id]);

            return self::SUCCESS;
        }

        $referenceDate = $testResult->collected_date ?? $testResult->reported_date;
        $age           = $this->resolvePatientAge($patient, $referenceDate ? Carbon::parse($referenceDate) : null);

        $this->line('  patient_id : ' . $patient->id);
        $this->line('  icno       : ' . ($patient->icno ?? 'null'));
        $this->line('  dob        : ' . ($patient->dob ?? 'null'));
        $this->line('  age        : ' . ($age ?? 'null (could not be resolved)'));

        $bmi = null;

        if ($patient->icno) {
            try {
                $bmi = app(MyHealthService::class)->getPatientBMI($patient->icno, $referenceDate);
                $this->line('  bmi        : ' . ($bmi ?? 'null (not found in MyHealth)'));
            } catch (Throwable $e) {
                $this->warn('  bmi        : null (MyHealth error: ' . $e->getMessage() . ')');
            }
        } else {
            $this->line('  bmi        : null (icno is null, cannot query MyHealth)');
        }

        $patientData = [
            'tc'            => $extractedValues['tc'],
            'ldlc'          => $extractedValues['ldlc'],
            'egfr'          => $extractedValues['egfr'],
            'hba1c_percent' => $extractedValues['hba1c_percent'],
            'alt'           => $extractedValues['alt'],
            'age'           => $age,
            'bmi'           => $bmi,
        ];

        $this->line('');
        $this->line('  Patient data fed into condition evaluator:');
        foreach ($patientData as $key => $val) {
            $this->line('    ' . str_pad($key, 14) . ': ' . ($val ?? 'null'));
        }

        $this->line('');

        // ── Deep diagnostic: condition evaluation ───────────────────────────
        $this->line('--- Condition Evaluation ---');

        $conditionEvaluator = app(ConditionEvaluatorService::class);
        $sortedIds          = ClinicalCondition::getIdsSortedByPriority();

        $matched = false;

        foreach ($sortedIds as $conditionId) {
            $condition = ClinicalCondition::getCondition($conditionId);
            $passes    = $conditionEvaluator->evaluateCondition($conditionId, $patientData);
            $label     = $condition['description'] ?? "Condition {$conditionId}";

            if ($passes) {
                $this->info("  [MATCH] Condition {$conditionId}: {$label}");
                $matched = true;
                break;
            } else {
                $this->line("  [    ] Condition {$conditionId}: {$label}");
            }
        }

        $this->line('');

        if (! $matched) {
            $this->warn('RESULT: NOT ELIGIBLE — no clinical condition matched (healthy patient based on current lab values).');
            Log::info('CheckConsultCallEligibility: Not eligible - no condition matched', ['test_result_id' => $id]);

            return self::SUCCESS;
        }

        // ── All checks passed — optionally create the record ─────────────────
        if ($dryRun) {
            $this->info('DRY RUN: condition matched — would create ConsultCall record. No record written.');
            Log::info('CheckConsultCallEligibility: Dry run stop - condition matched', ['test_result_id' => $id]);

            return self::SUCCESS;
        }

        $this->line('  Running checkAndCreate...');

        try {
            app(ConsultCallEligibilityService::class)->checkAndCreate(
                $testResult,
                $testResult->patient_id,
                $customerId,
                $outletId
            );
        } catch (Throwable $e) {
            $this->error('ERROR during eligibility check: ' . $e->getMessage());
            Log::error('CheckConsultCallEligibility: Error', [
                'test_result_id' => $id,
                'error'          => $e->getMessage(),
                'file'           => $e->getFile(),
                'line'           => $e->getLine(),
            ]);

            return self::FAILURE;
        }

        $existsNow = ConsultCallDetails::where('test_result_id', $id)->exists();

        if ($existsNow) {
            $this->info('RESULT: ELIGIBLE — ConsultCall record was created.');
            Log::info('CheckConsultCallEligibility: Eligible', ['test_result_id' => $id]);
        } else {
            $this->warn('RESULT: ELIGIBLE condition matched but record was NOT created (re-enrollment gates blocked it — check the logs).');
            Log::info('CheckConsultCallEligibility: Eligible but blocked by re-enrollment gates', ['test_result_id' => $id]);
        }

        return self::SUCCESS;
    }

    private function resolvePatientAge(Patient $patient, ?Carbon $collectedDate): ?int
    {
        if ($patient->age !== null) {
            return (int) $patient->age;
        }

        if ($patient->dob && $collectedDate) {
            return calculatePatientAge($patient->dob, $collectedDate->toDateString());
        }

        if ($patient->dob) {
            return Carbon::parse($patient->dob)->age;
        }

        return null;
    }
}

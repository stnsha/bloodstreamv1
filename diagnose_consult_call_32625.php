<?php

/**
 * Diagnostic script for ConsultCall auto-enrollment failure.
 * test_result_id = 32625
 *
 * Run via: php artisan tinker --execute="require base_path('diagnose_consult_call_32625.php');"
 */

use App\Constants\ConsultCall\PanelPanelItem;
use App\Models\ConsultCall;
use App\Models\ConsultCallDetails;
use App\Models\ConsultCallFollowUp;
use App\Models\Lab;
use App\Models\Patient;
use App\Models\TestResult;
use App\Services\ConditionEvaluatorService;
use App\Services\MyHealthService;
use App\Services\OctopusApiService;
use Carbon\Carbon;

$testResultId = 32625;

echo "=============================================================\n";
echo "ConsultCall Enrollment Diagnostic - test_result_id={$testResultId}\n";
echo "=============================================================\n\n";

// ---------------------------------------------------------------
// Step 1: Load test result
// ---------------------------------------------------------------
echo "--- Step 1: Load TestResult ---\n";

$testResult = TestResult::find($testResultId);

if (! $testResult) {
    echo "[FAIL] TestResult not found for id={$testResultId}. Cannot proceed.\n";
    return;
}

echo "[OK] TestResult found.\n";
echo "     lab_no:          {$testResult->lab_no}\n";
echo "     ref_id:          " . ($testResult->ref_id ?? '(null)') . "\n";
echo "     collected_date:  " . ($testResult->collected_date ?? '(null)') . "\n";
echo "     reported_date:   " . ($testResult->reported_date ?? '(null)') . "\n";
echo "     pdf_path:        " . ($testResult->pdf_path ?? '(null)') . "\n";
echo "     patient_id:      " . ($testResult->patient_id ?? '(null)') . "\n";
echo "     lab_id:          " . ($testResult->lab_id ?? '(null)') . "\n\n";

// ---------------------------------------------------------------
// Step 2: Check hasPdf gate
// ---------------------------------------------------------------
echo "--- Step 2: Gate - hasPdf ---\n";

$hasPdf = ! empty($testResult->pdf_path);

if (! $hasPdf) {
    echo "[FAIL] pdf_path is empty. Consult call block is skipped entirely when hasPdf is false.\n\n";
} else {
    echo "[OK] pdf_path is set.\n\n";
}

// ---------------------------------------------------------------
// Step 3: Check patient_id
// ---------------------------------------------------------------
echo "--- Step 3: Gate - patient_id ---\n";

$patientId = $testResult->patient_id;

if (! $patientId) {
    echo "[FAIL] patient_id is null on TestResult. Consult call block requires a resolved patient_id.\n\n";
} else {
    echo "[OK] patient_id = {$patientId}\n\n";
}

// ---------------------------------------------------------------
// Step 4: Check collected_date gate (>= 2026-03-08)
// ---------------------------------------------------------------
echo "--- Step 4: Gate - collected_date >= 2026-03-08 ---\n";

$collectedDate = $testResult->collected_date ?? $testResult->reported_date;
$consultCutoff = Carbon::parse('2026-03-08')->startOfDay();

if (! $collectedDate) {
    echo "[FAIL] No collected_date or reported_date on TestResult.\n\n";
} elseif (Carbon::parse($collectedDate)->lt($consultCutoff)) {
    echo "[FAIL] collected_date ({$collectedDate}) is before 2026-03-08. Consult call is skipped.\n\n";
} else {
    echo "[OK] collected_date ({$collectedDate}) is on or after 2026-03-08.\n\n";
}

// ---------------------------------------------------------------
// Step 5: Check ref_id gate
// ---------------------------------------------------------------
echo "--- Step 5: Gate - ref_id present ---\n";

$refId = $testResult->ref_id;

if (! $refId) {
    echo "[FAIL] ref_id is null/empty. Consult call is skipped without a ref_id.\n\n";
} else {
    echo "[OK] ref_id = {$refId}\n\n";
}

// ---------------------------------------------------------------
// Step 6: OctopusAPI eligibility check
// ---------------------------------------------------------------
echo "--- Step 6: OctopusAPI - eligibleConsultCallByOutlet ---\n";

$eligibleCustomer = null;
$labCode          = null;

if ($refId && $collectedDate) {
    $lab = Lab::find($testResult->lab_id);
    $labCode = $lab->code ?? null;

    echo "     lab_code: " . ($labCode ?? '(null)') . "\n";

    $multiOutletCutoff  = Carbon::parse('2026-04-06')->startOfDay();
    $collectedForBranch = Carbon::parse($collectedDate);

    $apiMethod = $collectedForBranch->gte($multiOutletCutoff)
        ? 'eligibleConsultCallByOutlet'
        : 'customerMelakaByRefId';

    echo "     API method used: {$apiMethod} (collected_date vs 2026-04-06 cutoff)\n";

    try {
        $octopusApi = app(OctopusApiService::class);

        if ($apiMethod === 'eligibleConsultCallByOutlet') {
            $eligibleCustomer = $octopusApi->eligibleConsultCallByOutlet($refId, $labCode);
        } else {
            $eligibleCustomer = $octopusApi->customerMelakaByRefId($refId, $labCode);
        }

        if (! $eligibleCustomer) {
            echo "[FAIL] OctopusAPI returned null - ref_id '{$refId}' does not match an eligible outlet customer.\n\n";
        } else {
            echo "[OK] Eligible customer found.\n";
            echo "     customer_id: " . ($eligibleCustomer['customer_id'] ?? '(null)') . "\n";
            echo "     outlet_id:   " . ($eligibleCustomer['outlet_id'] ?? '(null)') . "\n\n";
        }
    } catch (\Throwable $e) {
        echo "[FAIL] OctopusAPI call threw an exception: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "[SKIP] Skipped because ref_id or collected_date is missing.\n\n";
}

// ---------------------------------------------------------------
// Step 7: Duplicate guard - ConsultCallDetails already exists?
// ---------------------------------------------------------------
echo "--- Step 7: Duplicate guard - ConsultCallDetails for this test_result_id ---\n";

$existingDetails = ConsultCallDetails::where('test_result_id', $testResultId)->get();

if ($existingDetails->isNotEmpty()) {
    echo "[FAIL] ConsultCallDetails already exist for test_result_id={$testResultId}. Service would have returned early.\n";
    foreach ($existingDetails as $d) {
        echo "     detail_id={$d->id}, consult_call_id={$d->consult_call_id}, clinical_condition_id={$d->clinical_condition_id}\n";
    }
    echo "\n";
} else {
    echo "[OK] No existing ConsultCallDetails for this test_result_id.\n\n";
}

// ---------------------------------------------------------------
// Step 8: Check panel items (required categories)
// ---------------------------------------------------------------
echo "--- Step 8: Panel items - required categories ---\n";

$items = $testResult->testResultItems()
    ->whereIn('panel_panel_item_id', PanelPanelItem::ALL_IDS)
    ->get();

echo "     Items matched from PanelPanelItem::ALL_IDS: " . $items->count() . "\n";

if ($items->isNotEmpty()) {
    foreach ($items as $item) {
        echo "     panel_panel_item_id={$item->panel_panel_item_id}, value=" . ($item->value ?? '(null)') . "\n";
    }
}

$panelItemIds = $items->pluck('panel_panel_item_id')->toArray();
$allCategoriesPresent = true;

echo "\n     Checking required categories:\n";
foreach (PanelPanelItem::REQUIRED_CATEGORIES as $category => $ids) {
    $found = false;
    foreach ($ids as $id) {
        if (in_array($id, $panelItemIds)) {
            $found = true;
            break;
        }
    }
    $status = $found ? '[OK]  ' : '[MISS]';
    echo "     {$status} {$category} (IDs: " . implode(', ', $ids) . ")\n";
    if (! $found) {
        $allCategoriesPresent = false;
    }
}

if (! $allCategoriesPresent) {
    echo "\n[FAIL] Not all required categories are present. Service would have returned early (incomplete panel).\n\n";
} else {
    echo "\n[OK] All required categories present.\n\n";
}

// ---------------------------------------------------------------
// Step 9: Patient lookup
// ---------------------------------------------------------------
echo "--- Step 9: Patient lookup ---\n";

$patient = $patientId ? Patient::find($patientId) : null;

if (! $patient) {
    echo "[FAIL] Patient with id={$patientId} not found.\n\n";
} else {
    $referenceDate = $testResult->collected_date ?? $testResult->reported_date;
    $dob           = $patient->dob;
    $age           = $patient->age;
    $icno          = $patient->icno;

    if ($age === null && $dob && $referenceDate) {
        $age = Carbon::parse($dob)->diffInYears(Carbon::parse($referenceDate));
    } elseif ($age === null && $dob) {
        $age = Carbon::parse($dob)->age;
    }

    echo "[OK] Patient found.\n";
    echo "     patient.age:   " . ($patient->age ?? '(null - will be calculated)') . "\n";
    echo "     patient.dob:   " . ($patient->dob ?? '(null)') . "\n";
    echo "     patient.icno:  " . ($patient->icno ?? '(null)') . "\n";
    echo "     resolved age:  " . ($age ?? '(null)') . "\n\n";
}

// ---------------------------------------------------------------
// Step 10: Build patientData and resolve BMI
// ---------------------------------------------------------------
echo "--- Step 10: Build patientData and BMI ---\n";

$patientData = [];

if ($patient && $allCategoriesPresent) {
    $referenceDate  = $testResult->collected_date ?? $testResult->reported_date;
    $carbonRef      = $referenceDate ? Carbon::parse($referenceDate) : null;

    $resolvedAge = null;
    if ($patient->age !== null) {
        $resolvedAge = (int) $patient->age;
    } elseif ($patient->dob && $carbonRef) {
        $resolvedAge = (int) Carbon::parse($patient->dob)->diffInYears($carbonRef);
    } elseif ($patient->dob) {
        $resolvedAge = (int) Carbon::parse($patient->dob)->age;
    }

    $bmi = null;
    if ($patient->icno) {
        try {
            $myHealth = app(MyHealthService::class);
            $bmi      = $myHealth->getPatientBMI($patient->icno, $referenceDate);
            echo "     BMI from MyHealth: " . ($bmi ?? '(null)') . "\n";
        } catch (\Throwable $e) {
            echo "     BMI lookup failed (non-fatal): " . $e->getMessage() . "\n";
        }
    } else {
        echo "     BMI: skipped (no icno)\n";
    }

    $getPanelValue = function ($category) use ($items) {
        $ids = PanelPanelItem::REQUIRED_CATEGORIES[$category] ?? [];
        foreach ($ids as $id) {
            $item = $items->firstWhere('panel_panel_item_id', $id);
            if ($item && $item->value !== null && $item->value !== '') {
                return (float) $item->value;
            }
        }
        return null;
    };

    $patientData = [
        'tc'            => $getPanelValue('tc'),
        'ldlc'          => $getPanelValue('ldlc'),
        'egfr'          => $getPanelValue('egfr'),
        'hba1c_percent' => $getPanelValue('hba1c_percent'),
        'alt'           => $getPanelValue('alt'),
        'age'           => $resolvedAge,
        'bmi'           => $bmi,
    ];

    echo "\n     patientData used for condition evaluation:\n";
    foreach ($patientData as $key => $value) {
        echo "       {$key}: " . ($value !== null ? $value : '(null)') . "\n";
    }
    echo "\n";
} else {
    echo "[SKIP] Skipped because patient not found or required categories are missing.\n\n";
}

// ---------------------------------------------------------------
// Step 11: Condition evaluation
// ---------------------------------------------------------------
echo "--- Step 11: Condition evaluation ---\n";

$conditionId = null;

if (! empty($patientData)) {
    $evaluator   = app(ConditionEvaluatorService::class);
    $conditionId = $evaluator->evaluateSinglePatient($patientData);

    if ($conditionId === null) {
        echo "[INFO] Patient is healthy - no condition matched. Will proceed to healthy re-enrollment check.\n\n";
    } else {
        echo "[OK] Condition matched: condition_id={$conditionId}\n\n";
    }
} else {
    echo "[SKIP] Skipped because patientData is empty.\n\n";
}

// ---------------------------------------------------------------
// Step 12: Existing ConsultCall re-enrollment gates
// ---------------------------------------------------------------
echo "--- Step 12: Existing ConsultCall gates (only if consult_call already exists) ---\n";

$customerId = isset($eligibleCustomer['customer_id']) ? (int) $eligibleCustomer['customer_id'] : null;

if ($customerId && $patientId) {
    $existingConsultCall = ConsultCall::where('patient_id', $patientId)
        ->where('customer_id', $customerId)
        ->first();

    if (! $existingConsultCall) {
        echo "[OK] No existing ConsultCall for patient_id={$patientId}, customer_id={$customerId}. Fresh enrollment would be attempted.\n\n";
    } else {
        echo "[INFO] Existing ConsultCall found: consult_call_id={$existingConsultCall->id}\n";

        $latestFollowUp = $existingConsultCall->followUps()->latest()->first();
        $latestDetail   = $existingConsultCall->details()->latest()->first();

        echo "     Latest follow-up: " . ($latestFollowUp
            ? "id={$latestFollowUp->id}, followup_type={$latestFollowUp->followup_type} (BLOOD_TEST_AND_REVIEW=" . ConsultCallFollowUp::FOLLOWUP_TYPE_BLOOD_TEST_AND_REVIEW . ")"
            : '(none)') . "\n";

        echo "     Latest detail:    " . ($latestDetail
            ? "id={$latestDetail->id}, action=" . ($latestDetail->action ?? '(null)') . " (END_PROCESS=" . ConsultCallDetails::ACTION_END_PROCESS . ")"
            : '(none)') . "\n";

        // Gate 1 check
        if (
            ! $latestFollowUp ||
            $latestFollowUp->followup_type !== ConsultCallFollowUp::FOLLOWUP_TYPE_BLOOD_TEST_AND_REVIEW
        ) {
            echo "\n[FAIL] Gate 1: Latest follow-up is missing or not BLOOD_TEST_AND_REVIEW. Re-enrollment would be skipped.\n\n";
        } else {
            echo "\n[OK] Gate 1 passed: latest follow-up is BLOOD_TEST_AND_REVIEW.\n";

            // Gate 2 check
            if ($latestDetail && $latestDetail->action === ConsultCallDetails::ACTION_END_PROCESS) {
                echo "[FAIL] Gate 2: Last detail action is END_PROCESS. Re-enrollment would be skipped.\n\n";
            } else {
                echo "[OK] Gate 2 passed: last detail action is not END_PROCESS.\n\n";
            }
        }
    }
} else {
    echo "[SKIP] Skipped because customerId or patientId is not resolved.\n\n";
}

// ---------------------------------------------------------------
// Summary
// ---------------------------------------------------------------
echo "=============================================================\n";
echo "Diagnostic complete. Review [FAIL] lines above for root cause.\n";
echo "=============================================================\n";

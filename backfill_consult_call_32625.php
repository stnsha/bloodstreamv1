<?php

/**
 * Manual backfill: trigger ConsultCall enrollment for test_result_id = 32625.
 *
 * Run via: php artisan tinker --execute="require base_path('backfill_consult_call_32625.php');"
 *
 * This script is safe to run multiple times - ConsultCallEligibilityService has a
 * duplicate guard that will skip if ConsultCallDetails already exists for this test_result_id.
 */

use App\Models\Lab;
use App\Models\TestResult;
use App\Services\ConsultCallEligibilityService;
use App\Services\OctopusApiService;
use Carbon\Carbon;

$testResultId = 32625;

echo "=============================================================\n";
echo "ConsultCall Backfill - test_result_id={$testResultId}\n";
echo "=============================================================\n\n";

$testResult = TestResult::find($testResultId);

if (! $testResult) {
    echo "[FAIL] TestResult not found. Aborting.\n";
    return;
}

$patientId = $testResult->patient_id;

if (! $patientId) {
    echo "[FAIL] patient_id is null on TestResult. Aborting.\n";
    return;
}

$refId = $testResult->ref_id;

if (! $refId) {
    echo "[FAIL] ref_id is null on TestResult. Aborting.\n";
    return;
}

$collectedDate      = $testResult->collected_date ?? $testResult->reported_date;
$multiOutletCutoff  = Carbon::parse('2026-04-06')->startOfDay();
$collectedForBranch = Carbon::parse($collectedDate);

$lab     = Lab::find($testResult->lab_id);
$labCode = $lab->code ?? null;

echo "test_result_id:  {$testResultId}\n";
echo "patient_id:      {$patientId}\n";
echo "ref_id:          {$refId}\n";
echo "collected_date:  {$collectedDate}\n";
echo "lab_code:        " . ($labCode ?? '(null)') . "\n\n";

// Resolve eligible customer via OctopusAPI (same logic as ProcessPanelResults)
$octopusApi = app(OctopusApiService::class);

if ($collectedForBranch->gte($multiOutletCutoff)) {
    echo "API method: eligibleConsultCallByOutlet\n";
    $eligibleCustomer = $octopusApi->eligibleConsultCallByOutlet($refId, $labCode);
} else {
    echo "API method: customerMelakaByRefId\n";
    $eligibleCustomer = $octopusApi->customerMelakaByRefId($refId, $labCode);
}

if (! $eligibleCustomer) {
    echo "[FAIL] OctopusAPI returned null. Customer not found or not an eligible outlet. Aborting.\n";
    return;
}

$consultCustomerId = (int) $eligibleCustomer['customer_id'];
$consultOutletId   = isset($eligibleCustomer['outlet_id']) ? (int) $eligibleCustomer['outlet_id'] : null;

echo "customer_id:     {$consultCustomerId}\n";
echo "outlet_id:       " . ($consultOutletId ?? '(null)') . "\n\n";

echo "Calling ConsultCallEligibilityService::checkAndCreate()...\n\n";

app(ConsultCallEligibilityService::class)->checkAndCreate(
    $testResult,
    $patientId,
    $consultCustomerId,
    $consultOutletId
);

echo "Done. Check laravel.log and consult_call / consult_call_details tables for the result.\n";

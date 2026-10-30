<?php

namespace App\Services;

use App\Models\FailedValidation;
use App\Models\Patient;
use App\Models\TestResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies the ODB identity found by RefIdVerificationService to Blood Stream.
 *
 * Once a ref_id is confirmed as the patient's own sale (verified / corrected /
 * filled), the customer registered at that sale is the source of truth for the
 * patient name: Innoquest sometimes syncs a wrong name. The IC is never changed
 * automatically, because either side can carry the typo; a differing IC is
 * recorded in failed_validations (reason ic_mismatch) for manual review.
 *
 * Used at ingestion (ProcessPanelResults) and again right before a result is
 * sent to the MyHealth feed (verifyForFeed), so results stored before this
 * check existed are corrected before they leave the system.
 */
class PatientIdentityService
{
    const IC_MISMATCH_REASON = 'ic_mismatch';

    protected RefIdVerificationService $refIdVerification;

    public function __construct(RefIdVerificationService $refIdVerification)
    {
        $this->refIdVerification = $refIdVerification;
    }

    /**
     * Name registered at the ODB sale when the outcome identifies the patient, else null.
     */
    public function odbNameFromOutcome(array $outcome): ?string
    {
        if (! in_array($outcome['status'] ?? null, RefIdVerificationService::IDENTIFIED_STATUSES, true)) {
            return null;
        }

        return $outcome['customer_name'] ?? null;
    }

    /**
     * Replace the patient name with the ODB name when they differ (ignoring case,
     * punctuation and spacing). Non-critical: a failure is logged and the
     * existing name is kept.
     */
    public function applyOdbName(Patient $patient, ?string $odbName, array $context = []): bool
    {
        if (! $odbName) {
            return false;
        }

        $current = $this->refIdVerification->normalizeName($patient->name);

        if ($current === $this->refIdVerification->normalizeName($odbName)) {
            return false;
        }

        $previousName = $patient->name;

        try {
            DB::beginTransaction();

            $patient->update(['name' => $odbName]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('PatientIdentityService: failed to apply ODB name to patient, keeping existing name', $context + [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        // error level so the change stays visible under production LOG_LEVEL=error
        Log::error('PatientIdentityService: patient name replaced with ODB customer name', $context + [
            'patient_id' => $patient->id,
            'icno' => $patient->icno,
            'previous_name' => $previousName,
            'odb_name' => $odbName,
        ]);

        return true;
    }

    /**
     * Record a delivered IC that differs from the ODB customer IC behind a
     * confirmed sale. One row per lab_no; the IC itself is not changed.
     */
    public function recordIcConflict(string $requestId, int $labId, ?string $labNo, array $outcome, string $deliveredIc, ?string $deliveredName): void
    {
        if (empty($outcome['ic_conflict'])) {
            return;
        }

        $details = [
            'ref_id' => $outcome['ref_id'] ?? null,
            'status' => $outcome['status'] ?? null,
            'lab_ic' => $deliveredIc,
            'lab_name' => $deliveredName,
            'odb_ic' => $outcome['customer_ic'] ?? null,
            'odb_name' => $outcome['customer_name'] ?? null,
        ];

        try {
            $alreadyRecorded = $labNo !== null && FailedValidation::where('lab_no', $labNo)
                ->where('reason', self::IC_MISMATCH_REASON)
                ->exists();

            if ($alreadyRecorded) {
                return;
            }

            DB::beginTransaction();

            FailedValidation::create([
                'request_id' => $requestId,
                'lab_id' => $labId,
                'lab_no' => $labNo,
                'reason' => self::IC_MISMATCH_REASON,
                'missing_details' => sprintf(
                    'Lab IC %s differs from ODB IC %s for ref_id %s',
                    $deliveredIc,
                    $details['odb_ic'] ?? '-',
                    $details['ref_id'] ?? '-'
                ),
                'payload' => $details,
            ]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('PatientIdentityService: failed to record IC mismatch', $details + [
                'lab_no' => $labNo,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        Log::error('PatientIdentityService: IC mismatch recorded for manual review', $details + [
            'lab_no' => $labNo,
        ]);
    }

    /**
     * Re-run the ref_id verification for a stored result right before it is sent
     * to the MyHealth feed: fill a missing ref_id, replace a wrong patient name
     * with the ODB name and record an IC mismatch. A stored non-empty ref_id is
     * never changed here. Fail-open: any error is logged and the feed goes out
     * with the data as stored.
     */
    public function verifyForFeed(TestResult $testResult): void
    {
        $context = [
            'test_result_id' => $testResult->id,
            'lab_no' => $testResult->lab_no,
            'ref_id' => $testResult->ref_id,
        ];

        try {
            $testResult->loadMissing(['patient', 'doctor.lab']);
            $patient = $testResult->patient;
            $labCode = $testResult->doctor->lab->code ?? null;

            if (! $patient || ! $patient->icno) {
                return;
            }

            Log::info('PatientIdentityService: verifying identity before MyHealth feed', $context);

            $outcome = $this->refIdVerification->verify(
                $testResult->ref_id,
                $labCode,
                $patient->icno,
                $patient->name,
                $testResult->collected_date ? (string) $testResult->collected_date : null,
                $testResult->lab_no,
                $patient->dob ? (string) $patient->dob : null
            );

            if ($outcome['status'] === 'filled' && ! $testResult->ref_id) {
                $this->fillRefId($testResult, $outcome['ref_id'], $context);
            }

            $this->applyOdbName($patient, $this->odbNameFromOutcome($outcome), $context);

            $this->recordIcConflict(
                'myhealth-feed-'.$testResult->id,
                (int) ($testResult->doctor->lab_id ?? 0),
                $testResult->lab_no,
                $outcome,
                $patient->icno,
                $patient->name
            );

            Log::info('PatientIdentityService: identity verified before MyHealth feed', $context + [
                'status' => $outcome['status'],
                'ic_conflict' => $outcome['ic_conflict'],
            ]);
        } catch (Throwable $e) {
            Log::error('PatientIdentityService: identity verification before MyHealth feed failed, sending as stored', $context + [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function fillRefId(TestResult $testResult, string $refId, array $context): void
    {
        try {
            DB::beginTransaction();

            $testResult->update(['ref_id' => $refId]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('PatientIdentityService: failed to fill missing ref_id before MyHealth feed', $context + [
                'filled_ref_id' => $refId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        Log::error('PatientIdentityService: missing ref_id filled before MyHealth feed', $context + [
            'filled_ref_id' => $refId,
        ]);
    }
}

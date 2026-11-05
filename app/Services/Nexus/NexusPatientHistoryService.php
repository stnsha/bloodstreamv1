<?php

namespace App\Services\Nexus;

use App\Models\Patient;
use App\Models\TestResult;
use App\Services\TestResultCompilerService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Builds the completed test result history of one patient (by IC number) for
 * the Nexus integration, across every lab. Pending (is_completed = 0) results
 * are not returned.
 *
 * Each test result carries its raw lab data in the same format as
 * ai_reviews.compiled_results "Blood Test Results": panel name => list of
 * items (panel_item_name, result_value, panel_item_unit, result_status,
 * reference_range, comments), plus "SPECIAL TESTS" when special tests are
 * stored for the result.
 *
 * Read-only: no database writes, no MyHealth or ODB calls.
 */
class NexusPatientHistoryService
{
    public function __construct(
        protected TestResultCompilerService $compiler
    ) {}

    /**
     * Returns ['data' => <response data>, 'stats' => <counts for logging>],
     * or null when no patient has this IC number.
     */
    public function build(string $icno): ?array
    {
        $patient = Patient::where('icno', $icno)->first();

        if ($patient === null) {
            return null;
        }

        $testResults = $patient->testResults()
            ->where('is_completed', true)
            ->with([...$this->compiler->getEagerLoadRelations(), 'doctor.lab'])
            ->orderByDesc('collected_date')
            ->get();

        $withoutItems = 0;

        $history = $testResults->map(function (TestResult $testResult) use ($patient, &$withoutItems) {
            try {
                $bloodTestResults = $this->compiler->compileBloodTestItems($testResult);
            } catch (RuntimeException $e) {
                // A result with no valid items is still part of the history; it is
                // returned with an empty "blood_test_results" object.
                $withoutItems++;
                $bloodTestResults = [];

                Log::warning('NexusPatientHistoryService: test result has no valid items', [
                    'test_result_id' => $testResult->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $lab = $testResult->doctor?->lab;

            return [
                'test_result_id' => $testResult->id,
                'ref_id' => $testResult->ref_id,
                'lab_no' => $testResult->lab_no,
                'lab' => $lab ? ['code' => $lab->code, 'name' => $lab->name] : null,
                'collected_date' => $this->formatDateTime($testResult->collected_date),
                'reported_date' => $this->formatDateTime($testResult->reported_date),
                'status' => $testResult->is_completed ? 'Completed' : 'Pending',
                'age' => $this->calculateAge($patient->dob, $testResult->collected_date),
                'blood_test_results' => (object) $bloodTestResults,
            ];
        })->values()->all();

        return [
            'data' => [
                'patient' => [
                    'icno' => $patient->icno,
                    'name' => $patient->name,
                    'gender' => $patient->gender,
                    'dob' => $patient->dob,
                ],
                'test_results' => $history,
            ],
            'stats' => [
                'patient_id' => $patient->id,
                'test_result_count' => count($history),
                'without_items_count' => $withoutItems,
            ],
        ];
    }

    /**
     * Age at collection. Null when the DOB or collected date is missing or
     * cannot be parsed, so one bad record never fails the whole history.
     */
    protected function calculateAge(?string $dob, mixed $collectedDate): ?int
    {
        if (blank($dob) || blank($collectedDate)) {
            return null;
        }

        try {
            return calculatePatientAge($dob, $collectedDate);
        } catch (Exception $e) {
            Log::warning('NexusPatientHistoryService: age could not be calculated', [
                'dob' => $dob,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function formatDateTime(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }
}

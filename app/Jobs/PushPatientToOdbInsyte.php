<?php

namespace App\Jobs;

use App\Exceptions\OdbInsytePushException;
use App\Models\TestResult;
use App\Services\OdbInsytePushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pushes the patient behind a freshly COMPLETED AI review to the ODB InSyte
 * Push API (2 years of blood tests, MyHealth check-record, doctor remarks).
 * Dispatched by ProcessAIWebhookResult after its transaction commits.
 */
class PushPatientToOdbInsyte implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 180;

    public $tries = 3;

    public $backoff = [60, 300, 900];

    public $uniqueFor = 600;

    public function __construct(public readonly int $testResultId)
    {
        $this->onQueue(config('services.odb_insyte.queue', 'odb-push'));
    }

    public function uniqueId(): string
    {
        return "odb_insyte_push_{$this->testResultId}";
    }

    public function handle(OdbInsytePushService $pushService): void
    {
        $context = ['test_result_id' => $this->testResultId, 'attempt' => $this->attempts()];

        Log::channel('odb-push')->info('PushPatientToOdbInsyte: job started', $context);

        $testResult = TestResult::with('patient')->find($this->testResultId);
        $patient = $testResult?->patient;

        if (! $patient || empty($patient->icno)) {
            Log::channel('odb-push')->warning('PushPatientToOdbInsyte: test result or patient IC not found, skipping', $context + [
                'test_result_found' => (bool) $testResult,
                'patient_id' => $testResult?->patient_id,
            ]);

            return;
        }

        $context += ['patient_id' => $patient->id, 'icno' => $patient->icno];

        try {
            $payload = $pushService->buildPayload($patient);

            if ($payload === null) {
                Log::channel('odb-push')->warning('PushPatientToOdbInsyte: nothing to push for patient, skipping', $context);

                return;
            }

            $response = $pushService->push($payload);

            Log::channel('odb-push')->info('PushPatientToOdbInsyte: job completed', $context + [
                'queued' => $response['queued'] ?? null,
                'queue_position' => $response['queue_position'] ?? null,
            ]);
        } catch (OdbInsytePushException $e) {
            Log::channel('odb-push')->error('PushPatientToOdbInsyte: push failed', $context + [
                'http_status' => $e->httpStatus,
                'retryable' => $e->retryable,
                'error' => $e->getMessage(),
            ]);

            if (! $e->retryable) {
                $this->fail($e);

                return;
            }

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('odb-push')->error('PushPatientToOdbInsyte: job failed permanently', [
            'test_result_id' => $this->testResultId,
            'error' => $exception->getMessage(),
        ]);
    }
}

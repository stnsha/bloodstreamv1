<?php

namespace App\Jobs\ConsultCall;

use App\Models\ClinicalCondition;
use App\Constants\ConsultCall\PanelPanelItem;
use App\Models\ConsultCallFlag;
use App\Models\TestResult;
use App\Services\ConditionEvaluatorService;
use App\Services\MyHealthService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessConsultCall implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    public $tries = 3;

    public $backoff = [60, 300, 900];

    public $uniqueFor = 3600;

    protected int $testResultId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $testResultId)
    {
        $this->testResultId = $testResultId;
        $this->onQueue('consult-call');
    }

    /**
     * Get the unique ID for the job
     */
    public function uniqueId(): string
    {
        return "process_consult_call_{$this->testResultId}";
    }

    /**
     * Execute the job.
     */
    public function handle(
        ConditionEvaluatorService $conditionEvaluator,
        MyHealthService $myHealthService
    ): void {
        $startTime = microtime(true);

        Log::info('ProcessConsultCall: Starting job', [
            'test_result_id' => $this->testResultId,
            'attempt' => $this->attempts(),
        ]);

        try {
            $testResult = TestResult::with(['patient', 'testResultItems'])->find($this->testResultId);

            if (!$testResult) {
                Log::warning('ProcessConsultCall: Test result not found', [
                    'test_result_id' => $this->testResultId,
                ]);
                return;
            }

            if (!$testResult->patient) {
                Log::warning('ProcessConsultCall: Patient not found for test result', [
                    'test_result_id' => $this->testResultId,
                ]);
                return;
            }

            // Check if patient has previous test results (first-time patient check)
            $previousResultCount = TestResult::where('patient_id', $testResult->patient_id)
                ->where('id', '!=', $testResult->id)
                ->count();

            if ($previousResultCount > 0) {
                Log::info('ProcessConsultCall: Skipping - patient has previous records', [
                    'test_result_id' => $this->testResultId,
                    'patient_id' => $testResult->patient_id,
                    'previous_count' => $previousResultCount,
                ]);
                return;
            }

            // Check if all required panel items are present
            $testResultItems = $testResult->testResultItems
                ->whereIn('panel_panel_item_id', PanelPanelItem::ALL_IDS)
                ->keyBy('panel_panel_item_id');

            if (!$this->hasRequiredCategories($testResultItems)) {
                Log::info('ProcessConsultCall: Skipping - missing required parameters', [
                    'test_result_id' => $this->testResultId,
                    'available_items' => $testResultItems->keys()->toArray(),
                ]);
                return;
            }

            // Get BMI from MyHealthService
            $patientIcno = $testResult->patient->icno;
            $referenceDate = $testResult->reported_date
                ? $testResult->reported_date->format('Y-m-d')
                : null;
            $bmi = $myHealthService->getPatientBMI($patientIcno, $referenceDate);

            // Build evaluatable data
            $evaluatableData = $this->buildEvaluatableData($testResultItems, $testResult->patient, $bmi);

            // Evaluate conditions (sorted by priority - most specific first)
            $sortedConditionIds = ClinicalCondition::getIdsSortedByPriority();
            $assignedConditionId = 0; // Default: healthy
            $conditionDescription = 'Healthy (no conditions met)';

            foreach ($sortedConditionIds as $conditionId) {
                if ($conditionEvaluator->evaluateCondition($conditionId, $evaluatableData)) {
                    $assignedConditionId = $conditionId;
                    $condition = ClinicalCondition::getCondition($conditionId);
                    $conditionDescription = $condition['description'] ?? null;
                    break;
                }
            }

            Log::info('ProcessConsultCall: Condition evaluated', [
                'test_result_id' => $this->testResultId,
                'condition_id' => $assignedConditionId,
                'condition_description' => $conditionDescription,
                'evaluatable_data' => $evaluatableData,
            ]);

            // Save to consult_call_flags table
            $consultCallFlag = $this->saveConsultCallFlag(
                $this->testResultId,
                $assignedConditionId,
                $conditionDescription
            );

            // Send to external API
            $this->sendToExternalApi($consultCallFlag);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('ProcessConsultCall: Job completed successfully', [
                'test_result_id' => $this->testResultId,
                'condition_id' => $assignedConditionId,
                'duration_ms' => $duration,
            ]);

        } catch (Exception $e) {
            Log::error('ProcessConsultCall: Job failed', [
                'test_result_id' => $this->testResultId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    /**
     * Check if all required categories have at least one value present
     */
    protected function hasRequiredCategories($testResultItems): bool
    {
        foreach (PanelPanelItem::REQUIRED_CATEGORIES as $category => $ids) {
            // Skip hba1c (mmol/mol) - we only need hba1c_percent
            if ($category === 'hba1c') {
                continue;
            }

            $hasValue = false;
            foreach ($ids as $id) {
                if (isset($testResultItems[$id]) && $testResultItems[$id]->value !== null && $testResultItems[$id]->value !== '') {
                    $hasValue = true;
                    break;
                }
            }

            if (!$hasValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build the evaluatable data array from test result items
     */
    protected function buildEvaluatableData($testResultItems, $patient, ?float $bmi): array
    {
        $age = $patient->age ?? ($patient->dob ? Carbon::parse($patient->dob)->age : null);

        return [
            'tc' => $this->getValueFromItems($testResultItems, PanelPanelItem::tc),
            'ldlc' => $this->getValueFromItems($testResultItems, PanelPanelItem::ldlc),
            'egfr' => $this->getValueFromItems($testResultItems, PanelPanelItem::egfr),
            'hba1c_percent' => $this->getValueFromItems($testResultItems, PanelPanelItem::hba1c_percent),
            'alt' => $this->getValueFromItems($testResultItems, PanelPanelItem::alt),
            'age' => $age,
            'bmi' => $bmi,
        ];
    }

    /**
     * Get the first available numeric value from test result items matching the given IDs
     */
    protected function getValueFromItems($testResultItems, array $ids): ?float
    {
        foreach ($ids as $id) {
            if (isset($testResultItems[$id]) && $testResultItems[$id]->value !== null && $testResultItems[$id]->value !== '') {
                $value = $testResultItems[$id]->value;
                if (is_numeric($value)) {
                    return (float) $value;
                }
            }
        }

        return null;
    }

    /**
     * Save the consult call flag to the database
     */
    protected function saveConsultCallFlag(int $testResultId, int $conditionId, ?string $conditionDescription): ConsultCallFlag
    {
        try {
            DB::beginTransaction();

            $consultCallFlag = ConsultCallFlag::updateOrCreate(
                ['test_result_id' => $testResultId],
                [
                    'condition_id' => $conditionId,
                    'condition_description' => $conditionDescription,
                    'api_sent' => false,
                    'api_sent_at' => null,
                    'api_response' => null,
                ]
            );

            DB::commit();

            Log::info('ProcessConsultCall: ConsultCallFlag saved', [
                'test_result_id' => $testResultId,
                'consult_call_flag_id' => $consultCallFlag->id,
                'condition_id' => $conditionId,
            ]);

            return $consultCallFlag;

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('ProcessConsultCall: Failed to save ConsultCallFlag', [
                'test_result_id' => $testResultId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send the consult call flag data to the external API
     */
    protected function sendToExternalApi(ConsultCallFlag $consultCallFlag): void
    {
        $apiUrl = config('services.consult_call.api_url');

        if (empty($apiUrl)) {
            Log::warning('ProcessConsultCall: API URL not configured, skipping external API call', [
                'test_result_id' => $consultCallFlag->test_result_id,
            ]);
            return;
        }

        try {
            $token = $this->getJwtToken();

            if (!$token) {
                Log::error('ProcessConsultCall: Failed to obtain JWT token', [
                    'test_result_id' => $consultCallFlag->test_result_id,
                ]);
                return;
            }

            $payload = [
                'test_result_id' => $consultCallFlag->test_result_id,
                'condition_id' => $consultCallFlag->condition_id,
                'condition_description' => $consultCallFlag->condition_description,
            ];

            $response = Http::withToken($token)
                ->timeout(30)
                ->post($apiUrl . '/api/v1/consult-call', $payload);

            // Handle token expiry - retry with fresh token
            if ($response->status() === 401) {
                Log::info('ProcessConsultCall: Token expired, retrying with fresh token', [
                    'test_result_id' => $consultCallFlag->test_result_id,
                ]);

                Cache::forget('consult_call_jwt_token');
                $token = $this->getJwtToken(true);

                if ($token) {
                    $response = Http::withToken($token)
                        ->timeout(30)
                        ->post($apiUrl . '/api/v1/consult-call', $payload);
                }
            }

            $consultCallFlag->update([
                'api_sent' => $response->successful(),
                'api_sent_at' => now(),
                'api_response' => $response->body(),
            ]);

            if ($response->successful()) {
                Log::info('ProcessConsultCall: External API call successful', [
                    'test_result_id' => $consultCallFlag->test_result_id,
                    'http_status' => $response->status(),
                ]);
            } else {
                Log::error('ProcessConsultCall: External API call failed', [
                    'test_result_id' => $consultCallFlag->test_result_id,
                    'http_status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }

        } catch (Exception $e) {
            Log::error('ProcessConsultCall: External API call exception', [
                'test_result_id' => $consultCallFlag->test_result_id,
                'error' => $e->getMessage(),
            ]);

            $consultCallFlag->update([
                'api_sent' => false,
                'api_sent_at' => now(),
                'api_response' => json_encode(['error' => $e->getMessage()]),
            ]);
        }
    }

    /**
     * Get JWT token for external API authentication with caching
     */
    protected function getJwtToken(bool $forceRefresh = false): ?string
    {
        $cacheKey = 'consult_call_jwt_token';

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 60 * 25, function () {
            $apiUrl = config('services.consult_call.api_url');
            $username = config('services.consult_call.username');
            $password = config('services.consult_call.password');

            if (empty($apiUrl) || empty($username) || empty($password)) {
                Log::warning('ProcessConsultCall: Missing API credentials');
                return null;
            }

            try {
                $response = Http::timeout(30)->post($apiUrl . '/api/login', [
                    'email' => $username,
                    'password' => $password,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['token'] ?? $data['access_token'] ?? null;
                }

                Log::error('ProcessConsultCall: Failed to obtain JWT token', [
                    'http_status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return null;

            } catch (Exception $e) {
                Log::error('ProcessConsultCall: JWT token request exception', [
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Handle a job failure
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ProcessConsultCall: Job failed permanently', [
            'test_result_id' => $this->testResultId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}

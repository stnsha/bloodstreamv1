<?php

namespace App\Jobs\Testing;

use App\Jobs\Innoquest\ProcessPanelResults;
use App\Models\TestResult;
use Illuminate\Support\Facades\Log;

class ProcessPanelResultsTesting extends ProcessPanelResults
{
    public function __construct(array $validatedData, string $requestId, int $labId)
    {
        parent::__construct($validatedData, $requestId, $labId);
        $this->onQueue('panel-testing');
    }

    protected function dispatchAIReview(TestResult $test_result): void
    {
        try {
            SendToAIServerTesting::dispatch($test_result->id);
            Log::info('Dispatched test result to AI TESTING server queue', [
                'test_result_id' => $test_result->id,
                'lab_no' => $test_result->lab_no ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch test result to AI TESTING server queue', [
                'test_result_id' => $test_result->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function trackDeliveryFile(int $lab_id, ?string $sending_facility, ?string $batch_id, array $validated): void
    {
        Log::info('Skipped DeliveryFile tracking (testing replay)', [
            'batch_id' => $batch_id,
        ]);
    }
}

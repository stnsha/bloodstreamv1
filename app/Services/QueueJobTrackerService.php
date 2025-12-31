<?php

namespace App\Services;

use App\Models\DeliveryFile;
use App\Models\TestResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class QueueJobTrackerService
{
    /**
     * Wait for async job completion by polling DeliveryFile and TestResult tables
     *
     * @param string $requestId Unique request identifier
     * @param array $jsonData Original JSON data sent to the job
     * @param int $labId Laboratory ID
     * @param int $maxWaitSeconds Maximum time to wait in seconds
     * @return array Job completion result with test_result_id and details
     * @throws Exception If job fails or times out
     */
    public function waitForJobCompletion(
        string $requestId,
        array $jsonData,
        int $labId,
        int $maxWaitSeconds = 300
    ): array {
        $startTime = microtime(true);
        $batchId = $jsonData['MessageControlID'] ?? null;
        $labNo = $this->extractLabNo($jsonData);

        Log::info('Starting job tracking', [
            'request_id' => $requestId,
            'batch_id' => $batchId,
            'lab_no' => $labNo,
            'max_wait' => $maxWaitSeconds
        ]);

        // Polling intervals: exponential backoff
        $intervals = [500000, 1000000, 2000000, 4000000, 8000000]; // 0.5s to 8s in microseconds
        $intervalIndex = 0;

        while ((microtime(true) - $startTime) < $maxWaitSeconds) {
            // Check for job completion
            $result = $this->checkJobCompletion($requestId, $batchId, $labNo, $labId);

            if ($result['completed']) {
                $elapsedTime = round(microtime(true) - $startTime, 2);

                Log::info('Job completed successfully', [
                    'request_id' => $requestId,
                    'test_result_id' => $result['test_result_id'],
                    'elapsed_time' => $elapsedTime . 's'
                ]);

                return $result;
            }

            // Check for job failure
            $failure = $this->checkJobFailure($requestId);
            if ($failure) {
                Log::error('Job failed during execution', [
                    'request_id' => $requestId,
                    'error' => $failure['error'],
                    'failed_at' => $failure['failed_at']
                ]);

                throw new Exception('Job failed: ' . $failure['error']);
            }

            // Sleep with exponential backoff
            $sleepMicroseconds = $intervals[$intervalIndex] ?? 10000000; // Max 10s
            usleep($sleepMicroseconds);

            // Increase interval index for next iteration (up to max)
            if ($intervalIndex < count($intervals) - 1) {
                $intervalIndex++;
            }
        }

        $elapsedTime = round(microtime(true) - $startTime, 2);

        Log::error('Job timeout', [
            'request_id' => $requestId,
            'elapsed_time' => $elapsedTime . 's',
            'max_wait' => $maxWaitSeconds . 's',
            'diagnostics' => $this->getJobDiagnostics($requestId, $batchId, $labNo)
        ]);

        throw new Exception('Job timeout after ' . $maxWaitSeconds . ' seconds');
    }

    /**
     * Check if job has completed by querying DeliveryFile and TestResult
     *
     * @param string $requestId Request identifier
     * @param string|null $batchId Batch ID from JSON data
     * @param string|null $labNo Lab number from JSON data
     * @param int $labId Laboratory ID
     * @return array Completion status and result data
     */
    protected function checkJobCompletion(
        string $requestId,
        ?string $batchId,
        ?string $labNo,
        int $labId
    ): array {
        // Primary tracking: Check DeliveryFile table
        if ($batchId) {
            $deliveryFile = DeliveryFile::where('lab_id', $labId)
                ->where('batch_id', $batchId)
                ->where('status', DeliveryFile::compl)
                ->first();

            if ($deliveryFile) {
                // DeliveryFile completed, now find the TestResult
                if ($labNo) {
                    $testResult = TestResult::where('lab_no', $labNo)->first();

                    if ($testResult) {
                        // Load panel name if available
                        $panelName = $testResult->testResultItems()
                            ->with('panelPanelItem.panel')
                            ->first()
                            ?->panelPanelItem
                            ?->panel
                            ?->name;

                        return [
                            'completed' => true,
                            'test_result_id' => $testResult->id,
                            'lab_no' => $testResult->lab_no,
                            'panel' => $panelName,
                            'delivery_file_id' => $deliveryFile->id
                        ];
                    }
                }

                // DeliveryFile exists but TestResult not found yet
                // This can happen if there's a slight delay in creation
                return ['completed' => false];
            }
        }

        // Secondary tracking: Check TestResult directly by lab_no
        if ($labNo) {
            $testResult = TestResult::where('lab_no', $labNo)
                ->whereNotNull('id')
                ->first();

            if ($testResult) {
                // Load panel name if available
                $panelName = $testResult->testResultItems()
                    ->with('panelPanelItem.panel')
                    ->first()
                    ?->panelPanelItem
                    ?->panel
                    ?->name;

                return [
                    'completed' => true,
                    'test_result_id' => $testResult->id,
                    'lab_no' => $testResult->lab_no,
                    'panel' => $panelName,
                    'delivery_file_id' => null
                ];
            }
        }

        return ['completed' => false];
    }

    /**
     * Check if job has failed by querying failed_jobs table
     *
     * @param string $requestId Request identifier
     * @return array|null Failure information or null if no failure
     */
    protected function checkJobFailure(string $requestId): ?array
    {
        $failedJob = DB::table('failed_jobs')
            ->where('payload', 'like', '%' . $requestId . '%')
            ->orderBy('failed_at', 'desc')
            ->first();

        if ($failedJob) {
            return [
                'error' => substr($failedJob->exception, 0, 500),
                'failed_at' => $failedJob->failed_at
            ];
        }

        return null;
    }

    /**
     * Extract lab number from JSON data structure
     *
     * @param array $jsonData JSON data from the request
     * @return string|null Lab number or null if not found
     */
    protected function extractLabNo(array $jsonData): ?string
    {
        if (isset($jsonData['Orders'][0]['Observations'][0]['FillerOrderNumber'])) {
            return $jsonData['Orders'][0]['Observations'][0]['FillerOrderNumber'];
        }

        return null;
    }

    /**
     * Get diagnostic information about job status
     *
     * @param string $requestId Request identifier
     * @param string|null $batchId Batch ID
     * @param string|null $labNo Lab number
     * @return array Diagnostic information
     */
    public function getJobDiagnostics(string $requestId, ?string $batchId, ?string $labNo): array
    {
        return [
            'jobs_pending' => DB::table('jobs')
                ->where('queue', 'panel')
                ->where('payload', 'like', '%' . $requestId . '%')
                ->count(),
            'delivery_file_status' => $batchId
                ? DeliveryFile::where('batch_id', $batchId)->first()?->status
                : null,
            'test_result_exists' => $labNo
                ? TestResult::where('lab_no', $labNo)->exists()
                : false,
            'failed_jobs' => DB::table('failed_jobs')
                ->where('payload', 'like', '%' . $requestId . '%')
                ->count()
        ];
    }
}

<?php

namespace App\Http\Controllers\API\ODB;

use App\Http\Controllers\Controller;
use App\Http\Requests\ODB\MigrateRequest;
use App\Http\Requests\ODB\ODBRequest;
use App\Jobs\ProcessMigrationBatch;
use App\Models\DoctorReview;
use App\Models\MigrationBatch;
use App\Models\MigrationBatchItem;
use App\Models\TestResult;
use App\Services\ODB\MigrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class BloodTestController extends Controller
{
    /**
     * Sync Test Result ID as Report ID to ODB
     */
    public function getReportId(ODBRequest $request)
    {
        try {
            $validated = $request->all();
            $results = [];

            foreach ($validated as $item) {
                $icno = $item['icno'];
                $refid = $item['refid'] ?? null;

                // Search by IC number first
                $testResult = TestResult::whereHas('patient', function ($p) use ($icno) {
                    $p->where('icno', $icno);
                })->first();

                // Fallback to search by refid if provided
                if (!$testResult && $refid) {
                    $testResult = TestResult::where('ref_id', $refid)->where('is_completed', true)->first();
                }

                // Update ref_id if request has refid but DB has null
                if ($testResult && $refid && !$testResult->ref_id) {
                    $testResult->ref_id = $refid;
                    $testResult->save();
                }

                // Only add to results if test result found
                if ($testResult) {
                    $results[] = [
                        'icno' => $icno,
                        'refid' => $refid,
                        'report_id' => $testResult->id
                    ];
                }
            }

            return response()->json($results);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync Doctor Review to ODB
     */
    public function sync(ODBRequest $request)
    {
        try {
            $validated = $request->all();
            $results = [];

            foreach ($validated as $item) {
                $icno = $item['icno'];
                $refid = $item['refid'] ?? null;

                // Search by IC number first
                $review = DoctorReview::with(['testResult', 'testResult.patient'])
                    ->where('is_sync', false)
                    ->whereHas('testResult.patient', function ($q) use ($icno) {
                        $q->where('icno', $icno);
                    })
                    ->first();

                // Fallback to search by refid if provided
                if (!$review && $refid) {
                    $review = DoctorReview::with(['testResult', 'testResult.patient'])
                        ->where('is_sync', false)
                        ->whereHas('testResult', function ($t) use ($refid) {
                            $t->where('ref_id', $refid);
                        })
                        ->first();
                }

                // Only add to results if review found
                if ($review) {
                    // Mark as synced
                    $review->is_sync = true;
                    $review->save();

                    $results[] = [
                        'icno' => $icno,
                        'refid' => $refid,
                        'review' => $review->review
                    ];
                }
            }

            return response()->json($results);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Migrate old data from ODB to MyHealth
     */
    public function migrate(MigrateRequest $request)
    {
        try {
            $validated = $request->validated();

            // Generate unique batch UUID
            $batchUuid = Str::uuid()->toString();

            // Create migration batch
            $batch = MigrationBatch::create([
                'batch_uuid' => $batchUuid,
                'total_reports' => count($validated['reports']),
                'status' => MigrationBatch::STATUS_PENDING,
            ]);

            // Create batch items
            foreach ($validated['reports'] as $report) {
                MigrationBatchItem::create([
                    'batch_id' => $batch->id,
                    'ref_id' => $report['ref_id'],
                    'report_data' => json_encode($report),
                    'status' => MigrationBatchItem::STATUS_PENDING,
                ]);
            }

            // Dispatch job to process batch asynchronously
            ProcessMigrationBatch::dispatch($batch->id);

            return response()->json([
                'success' => true,
                'message' => 'Migration batch created successfully',
                'data' => [
                    'batch_uuid' => $batchUuid,
                    'total_reports' => $batch->total_reports,
                    'status_url' => route('odb.migration.status', ['uuid' => $batchUuid]),
                ],
            ], 202);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create migration batch',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get migration batch status
     */
    public function migrationStatus($uuid)
    {
        try {
            $batch = MigrationBatch::where('batch_uuid', $uuid)->first();

            if (!$batch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batch not found',
                ], 404);
            }

            // Get failed items with error details
            $failedItems = $batch->failedItems()->get()->map(function ($item) {
                return [
                    'ref_id' => $item->ref_id,
                    'error' => $item->error_message,
                    'attempts' => $item->attempt_count,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'batch_uuid' => $batch->batch_uuid,
                    'status' => $batch->status,
                    'total' => $batch->total_reports,
                    'processed' => $batch->processed,
                    'success' => $batch->success,
                    'failed' => $batch->failed,
                    'started_at' => $batch->started_at?->toIso8601String(),
                    'completed_at' => $batch->completed_at?->toIso8601String(),
                    'failed_items' => $failedItems,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch migration status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test migration without jobs (for debugging with dd, var_dump, etc.)
     */
    public function migrateTest(MigrateRequest $request, MigrationService $migrationService)
    {
        try {
            $validated = $request->validated();

            // Process first report only for testing
            $report = $validated['reports'][0];

            // Process directly without job
            $testResult = $migrationService->processReport($report['report'], $report['parameter']);

            return response()->json([
                'success' => true,
                'message' => 'Test migration processed successfully',
                'data' => [
                    'test_result_id' => $testResult->id,
                    'ref_id' => $testResult->ref_id,
                    'lab_no' => $testResult->lab_no,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test migration failed',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }
}
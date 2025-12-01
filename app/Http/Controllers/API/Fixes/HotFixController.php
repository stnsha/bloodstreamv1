<?php

namespace App\Http\Controllers\API\Fixes;

use App\Http\Controllers\Controller;
use App\Models\TestResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HotFixController extends Controller
{
    /**
     * Normalize ref_id values to uppercase in test_results table
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function normalizeRefId()
    {
        $totalFound = 0;
        $totalUpdated = 0;
        $failedRecords = [];
        $processingStartTime = now();

        Log::info('normalizeRefId: Starting ref_id normalization process', [
            'timestamp' => $processingStartTime
        ]);

        try {
            DB::transaction(function () use (&$totalFound, &$totalUpdated, &$failedRecords) {
                // Find all records with lowercase characters in ref_id
                TestResult::whereNotNull('ref_id')
                    ->whereRaw('BINARY ref_id != UPPER(ref_id)')
                    ->chunk(100, function ($records) use (&$totalFound, &$totalUpdated, &$failedRecords) {
                        $totalFound += $records->count();

                        foreach ($records as $record) {
                            try {
                                $originalRefId = $record->ref_id;
                                $record->ref_id = strtoupper($originalRefId);

                                if ($record->save()) {
                                    $totalUpdated++;

                                    Log::debug('normalizeRefId: Updated record', [
                                        'test_result_id' => $record->id,
                                        'original_ref_id' => $originalRefId,
                                        'new_ref_id' => $record->ref_id
                                    ]);
                                } else {
                                    $failedRecords[] = [
                                        'id' => $record->id,
                                        'ref_id' => $originalRefId,
                                        'reason' => 'Save operation returned false'
                                    ];
                                }
                            } catch (Throwable $e) {
                                $failedRecords[] = [
                                    'id' => $record->id,
                                    'ref_id' => $record->ref_id,
                                    'reason' => $e->getMessage()
                                ];

                                Log::warning('normalizeRefId: Failed to update individual record', [
                                    'test_result_id' => $record->id,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                    });
            });

            $processingTime = now()->diffInSeconds($processingStartTime);
            $failedCount = count($failedRecords);
            $successRate = $totalFound > 0
                ? round(($totalUpdated / $totalFound) * 100, 2)
                : 100;

            Log::info('normalizeRefId: Normalization process completed', [
                'total_found' => $totalFound,
                'total_updated' => $totalUpdated,
                'failed_count' => $failedCount,
                'success_rate' => $successRate . '%',
                'processing_time' => $processingTime . 's',
                'failed_records' => $failedRecords
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ref ID normalization completed successfully',
                'data' => [
                    'total_found' => $totalFound,
                    'total_updated' => $totalUpdated,
                    'failed_count' => $failedCount,
                    'success_rate' => $successRate
                ]
            ], 200);

        } catch (Throwable $e) {
            Log::error('normalizeRefId: Critical error occurred', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'total_found' => $totalFound,
                'total_updated' => $totalUpdated
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to normalize ref_id values',
                'error' => 'Internal server error'
            ], 500);
        }
    }
}
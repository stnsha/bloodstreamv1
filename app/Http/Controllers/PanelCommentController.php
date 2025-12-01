<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API\BaseResultsController;
use App\Jobs\ProcessPanelComments;
use App\Models\DeliveryFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class PanelCommentController extends BaseResultsController
{
    public function update()
    {
        $user = Auth::guard('lab')->user();
        $lab_id = $user->lab_id;

        // Batch configuration
        $batchSize = 50; // Smaller batches for jobs
        $totalRecords = DeliveryFile::count();
        $totalBatches = ceil($totalRecords / $batchSize);

        Log::info("Starting job-based processing of {$totalRecords} delivery files in {$totalBatches} jobs");

        $currentBatch = 0;
        
        // Dispatch jobs for each batch
        DeliveryFile::select(['id'])
            ->chunk($batchSize, function ($files) use (&$currentBatch, $lab_id) {
                $currentBatch++;
                $fileIds = $files->pluck('id')->toArray();
                
                // Dispatch job with delay to prevent overwhelming
                ProcessPanelComments::dispatch($currentBatch, $fileIds, $lab_id)
                    ->delay(now()->addSeconds($currentBatch * 30)); // 30 second delay between jobs
            });

        return response()->json([
            'success' => true,
            'message' => "Successfully dispatched {$totalBatches} jobs to process {$totalRecords} delivery files",
            'data' => [
                'total_records' => $totalRecords,
                'total_jobs_dispatched' => $totalBatches,
                'batch_size' => $batchSize,
                'estimated_completion_time' => now()->addMinutes($totalBatches * 2)->format('Y-m-d H:i:s'),
                'status' => 'Jobs dispatched to queue - monitor logs for progress'
            ]
        ]);
    }
}
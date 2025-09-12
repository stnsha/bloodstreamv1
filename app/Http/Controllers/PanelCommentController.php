<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API\BaseResultsController;
use App\Http\Requests\InnoquestResultRequest;
use App\Jobs\ProcessPanelComments;
use App\Models\DeliveryFile;
use App\Models\PanelProfile;
use App\Models\TestResult;
use App\Models\TestResultProfile;
use App\Models\MasterPanelComment;
use App\Models\MasterPanelItem;
use App\Models\MasterPanel;
use App\Models\Panel;
use App\Models\PanelComment;
use App\Models\PanelItem;
use App\Models\PanelPanelItem;
use App\Models\ReferenceRange;
use App\Models\TestResultComment;
use App\Models\TestResultItem;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Stichoza\GoogleTranslate\GoogleTranslate;

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

    private function findOrCreateProfile($lab_id, $profile_code = null)
    {
        if (filled($profile_code)) {
            $panel_profile = PanelProfile::firstOrCreate(
                [
                    'lab_id' => $lab_id,
                    'code' => $profile_code,
                ],
                [
                    'name' => $profile_code,
                ]
            );

            return $panel_profile->id;
        }

        return null;
    }

    private function findOrCreatePanel($lab_id, $panel_code, $panel_name)
    {

        // 1. First, create or find master panel
        $masterPanel = MasterPanel::firstOrCreate([
            'name' => $panel_name
        ]);

        // 2. Create or get Panel with master panel reference
        $panel = Panel::firstOrCreate([
            'lab_id' => $lab_id,
            'master_panel_id' => $masterPanel->id,
            'code' => $panel_code,
        ], [
            'name' => $panel_name
        ]);

        return $panel;
    }
}
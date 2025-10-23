<?php

namespace App\Http\Controllers;

use App\Jobs\ExportBpJob;
use Illuminate\Support\Facades\Log;

class ExportController extends Controller
{
    public function exportBp() {
        try {
            Log::channel('bp-log')->info("BP Export job dispatched");

            // Dispatch job to background queue
            ExportBpJob::dispatch();

            return response()->json([
                'success' => true,
                'message' => 'BP export job has been queued. The file will be generated in the background. Check storage/excel folder or logs for progress.',
            ]);

        } catch (\Exception $e) {
            Log::channel('bp-log')->error("Error dispatching exportBp job: " . $e->getMessage());

            return response()->json([
                'error' => 'Failed to dispatch export job',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

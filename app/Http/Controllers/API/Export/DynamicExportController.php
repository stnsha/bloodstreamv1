<?php

namespace App\Http\Controllers\API\Export;

use App\Http\Controllers\Controller;
use App\Jobs\DynamicExportJob;
use App\Models\ExportJob;
use App\Services\DynamicExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DynamicExportController extends Controller
{
    private DynamicExportService $service;

    public function __construct(DynamicExportService $service)
    {
        $this->service = $service;
    }

    /**
     * Return all active master_panel_items.
     *
     * GET /api/export/dynamic/options
     */
    public function options(Request $request): JsonResponse
    {
        try {
            $items = $this->service->getMasterPanelItems();
            return response()->json(['items' => $items]);
        } catch (Throwable $e) {
            Log::error('DynamicExportController: options failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Return a count of test_results matching the given filters.
     *
     * POST /api/export/dynamic/count
     * Body: { date_from, date_to, master_panel_item_ids[] }
     */
    public function count(Request $request): JsonResponse
    {
        $request->validate([
            'date_from'                  => 'required|date',
            'date_to'                    => 'required|date|after_or_equal:date_from',
            'master_panel_item_ids'      => 'required|array|min:1',
            'master_panel_item_ids.*'    => 'integer',
        ]);

        try {
            $mpiIds = $request->input('master_panel_item_ids');

            Log::info('DynamicExportController: count', [
                'date_from' => $request->input('date_from'),
                'date_to'   => $request->input('date_to'),
                'mpi_count' => count($mpiIds),
            ]);

            $count = $this->service->countResults(
                $request->input('date_from'),
                $request->input('date_to'),
                $mpiIds
            );

            return response()->json(['count' => $count]);
        } catch (Throwable $e) {
            Log::error('DynamicExportController: count failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate a CSV extract and return it as base64.
     *
     * POST /api/export/dynamic
     * Body: { date_from, date_to, master_panel_item_ids[], columns[] }
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'date_from'                  => 'required|date',
            'date_to'                    => 'required|date|after_or_equal:date_from',
            'master_panel_item_ids'      => 'required|array|min:1',
            'master_panel_item_ids.*'    => 'integer',
            'columns'                    => 'sometimes|array',
            'columns.*'                  => 'string|in:lab_no,ref_id,collected_date,age,gender,outlet_code,race,regional,customer_name,nric,phone',
        ]);

        ini_set('memory_limit', '512M');
        set_time_limit(600);

        try {
            $mpiIds         = $request->input('master_panel_item_ids');
            $columns        = $request->input('columns', array());
            $includeOctopus = !empty(array_intersect($columns, ['race', 'regional', 'customer_name', 'nric', 'phone']));

            Log::info('DynamicExportController: export start', [
                'date_from'       => $request->input('date_from'),
                'date_to'         => $request->input('date_to'),
                'mpi_count'       => count($mpiIds),
                'columns'         => $columns,
                'include_octopus' => $includeOctopus,
            ]);

            $result   = $this->service->generateCsv(
                $request->input('date_from'),
                $request->input('date_to'),
                $mpiIds,
                $columns,
                $includeOctopus
            );

            if (!empty($result['error'])) {
                Log::warning('DynamicExportController: export blocked', ['reason' => $result['error']]);
                return response()->json(['error' => $result['error']], 422);
            }

            $filename = 'result_extract_' . date('Y-m-d_His') . '.csv';

            Log::info('DynamicExportController: export complete', [
                'filename'  => $filename,
                'row_count' => $result['row_count'],
            ]);

            return response()->json([
                'csv_base64' => $result['csv_base64'],
                'filename'   => $filename,
                'row_count'  => $result['row_count'],
                'warnings'   => $result['warnings'],
            ]);
        } catch (Throwable $e) {
            Log::error('DynamicExportController: export failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Queue a CSV export job and return a job UUID for polling.
     *
     * POST /api/export/dynamic/queue
     */
    public function queue(Request $request): JsonResponse
    {
        $request->validate([
            'date_from'               => 'required|date',
            'date_to'                 => 'required|date|after_or_equal:date_from',
            'master_panel_item_ids'   => 'required|array|min:1',
            'master_panel_item_ids.*' => 'integer',
            'columns'                 => 'sometimes|array',
            'columns.*'               => 'string|in:lab_no,ref_id,collected_date,age,gender,outlet_code,race,regional,customer_name,nric,phone',
        ]);

        try {
            $jobUuid = Str::uuid()->toString();

            ExportJob::create([
                'job_uuid' => $jobUuid,
                'status'   => ExportJob::STATUS_PENDING,
            ]);

            DynamicExportJob::dispatch(
                $jobUuid,
                $request->input('date_from'),
                $request->input('date_to'),
                $request->input('master_panel_item_ids'),
                $request->input('columns', [])
            );

            Log::info('DynamicExportController: job queued', ['job_uuid' => $jobUuid]);

            return response()->json(['job_uuid' => $jobUuid], 202);
        } catch (Throwable $e) {
            Log::error('DynamicExportController: queue failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Poll the status of a queued export job.
     *
     * GET /api/export/dynamic/status/{uuid}
     */
    public function status(Request $request, string $uuid): JsonResponse
    {
        try {
            $job = ExportJob::where('job_uuid', $uuid)->first();

            if (!$job) {
                return response()->json(['error' => 'Job not found.'], 404);
            }

            $response = [
                'status'       => $job->status,
                'row_count'    => $job->row_count,
                'warnings'     => $job->warnings ? json_decode($job->warnings, true) : [],
                'error'        => $job->error_message,
                'completed_at' => $job->completed_at?->toIso8601String(),
            ];

            if (
                $job->status === ExportJob::STATUS_COMPLETED &&
                !empty($job->result_path) &&
                file_exists($job->result_path)
            ) {
                $response['csv_base64'] = base64_encode(file_get_contents($job->result_path));
                $response['filename']   = 'result_extract_' . date('Y-m-d_His') . '.csv';
            }

            return response()->json($response);
        } catch (Throwable $e) {
            Log::error('DynamicExportController: status failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

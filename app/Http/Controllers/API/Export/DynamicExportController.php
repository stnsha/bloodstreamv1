<?php

namespace App\Http\Controllers\API\Export;

use App\Http\Controllers\Controller;
use App\Services\DynamicExportService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        } catch (Exception $e) {
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
        } catch (Exception $e) {
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
        } catch (Exception $e) {
            Log::error('DynamicExportController: export failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

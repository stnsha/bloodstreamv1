<?php

namespace App\Http\Controllers\API\ODB;

use App\Http\Controllers\Controller;
use App\Models\IncompleteTestResult;
use App\Models\TestResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class IncompleteTestResultsController extends Controller
{
    private function getLogChannel()
    {
        return 'odb-log';
    }

    /**
     * List all incomplete_test_results rows, paginated, with lab_no/ref_id
     * joined in from the related TestResult.
     */
    public function index(Request $request)
    {
        $page = max(1, (int) $request->input('page', 1));
        $search = trim((string) $request->input('search', ''));

        try {
            $query = IncompleteTestResult::with('testResult')
                ->orderBy('id', 'desc');

            if ($search !== '') {
                $query->whereHas('testResult', function ($q) use ($search) {
                    $q->where('lab_no', 'like', "%{$search}%")
                        ->orWhere('ref_id', 'like', "%{$search}%");
                });
            }

            $paginator = $query->paginate(30, ['*'], 'page', $page);

            $data = $paginator->getCollection()->map(function ($row) {
                return [
                    'test_result_id' => $row->test_result_id,
                    'lab_no' => $row->testResult->lab_no ?? null,
                    'ref_id' => $row->testResult->ref_id ?? null,
                    'expected_panel_count' => $row->expected_panel_count,
                    'actual_panel_count' => $row->actual_panel_count,
                    'was_reviewed' => $row->was_reviewed,
                    'reason' => $row->reason,
                    'missing_details' => $row->missing_details,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ]);
        } catch (Throwable $e) {
            Log::channel($this->getLogChannel())->error('IncompleteTestResultsController@index: Critical error occurred', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export lab_no/reason/missing_details for test_results that are
     * neither completed nor reviewed, optionally filtered by collected_date range.
     */
    public function exportUnreviewed(Request $request)
    {
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));

        Log::channel($this->getLogChannel())->info('IncompleteTestResultsController@exportUnreviewed: Starting export', [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        try {
            $query = TestResult::query()
                ->leftJoin('incomplete_test_results', 'incomplete_test_results.test_result_id', '=', 'test_results.id')
                ->where('test_results.is_completed', false)
                ->where('test_results.is_reviewed', false);

            if ($dateFrom !== '') {
                $query->whereDate('test_results.collected_date', '>=', $dateFrom);
            }

            if ($dateTo !== '') {
                $query->whereDate('test_results.collected_date', '<=', $dateTo);
            }

            $rows = $query->orderBy('test_results.collected_date', 'desc')
                ->get([
                    'test_results.lab_no',
                    'incomplete_test_results.reason',
                    'incomplete_test_results.missing_details',
                ]);

            Log::channel($this->getLogChannel())->info('IncompleteTestResultsController@exportUnreviewed: Export completed', [
                'row_count' => $rows->count(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $rows,
            ]);
        } catch (Throwable $e) {
            Log::channel($this->getLogChannel())->error('IncompleteTestResultsController@exportUnreviewed: Critical error occurred', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

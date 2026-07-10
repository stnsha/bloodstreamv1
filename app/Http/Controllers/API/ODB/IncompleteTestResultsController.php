<?php

namespace App\Http\Controllers\API\ODB;

use App\Http\Controllers\Controller;
use App\Models\IncompleteTestResult;
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
}

<?php

namespace App\Http\Controllers\API\General;

use App\Http\Controllers\Controller;
use App\Models\TestResultSpecialTest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class TestResultSpecialTestController extends Controller
{
    /**
     * Restrict the query to the caller's lab, unless the caller is the
     * superadmin lab (id 1), matching TestResultItemController::scopeToLab().
     */
    private function scopeToLab(Builder $query)
    {
        $user = Auth::guard('lab')->user();
        $lab_id = $user->lab_id;

        if ($lab_id !== 1) {
            $query->whereHas('testResult.doctor', function ($q) use ($lab_id) {
                $q->where('lab_id', $lab_id);
            });
        }

        return $lab_id;
    }

    private function respond(Builder $query, string $logContext, array $context = []): JsonResponse
    {
        try {
            $lab_id = $this->scopeToLab($query);

            Log::info("{$logContext}: fetching test result special tests", $context + ['lab_id' => $lab_id]);

            $items = $query->get(['test_result_id', 'panel_panel_item_id', 'value']);

            Log::info("{$logContext}: completed", $context + ['lab_id' => $lab_id, 'count' => $items->count()]);

            return response()->json([
                'success' => true,
                'data' => $items->map(fn ($item) => [
                    'test_result_id' => $item->test_result_id,
                    'panel_panel_item_id' => $item->panel_panel_item_id,
                    'value' => $item->value,
                ])->values(),
                'count' => $items->count(),
            ], 200);
        } catch (Throwable $e) {
            Log::error("{$logContext}: failed", $context + [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve test result special tests',
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * GET /api/test-result-special-tests
     * All test result special tests visible to the caller's lab. No pagination.
     */
    public function all(): JsonResponse
    {
        return $this->respond(TestResultSpecialTest::query(), 'TestResultSpecialTestController@all');
    }

    /**
     * GET /api/test-result-special-tests/panel-interpretation/{panelInterpretationId}
     */
    public function byPanelInterpretation($panelInterpretationId): JsonResponse
    {
        $query = TestResultSpecialTest::query()->where('panel_interpretation_id', $panelInterpretationId);

        return $this->respond($query, 'TestResultSpecialTestController@byPanelInterpretation', ['panel_interpretation_id' => $panelInterpretationId]);
    }
}

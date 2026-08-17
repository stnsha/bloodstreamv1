<?php

namespace App\Http\Controllers\API\MyHealth;

use App\Http\Controllers\Controller;
use App\Services\MyHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class MyHealthController extends Controller
{
    protected MyHealthService $myHealthService;

    public function __construct(MyHealthService $myHealthService)
    {
        $this->myHealthService = $myHealthService;
    }

    /**
     * GET /api/v1/myhealth/check-record/{ic}
     * All check_record data for an IC, across every visit (no date
     * restriction), flattened into one parameter -> {value, range, unit}
     * map. Where the same parameter appears in more than one visit, the
     * most recent visit's value wins.
     */
    public function checkRecordByIc($ic): JsonResponse
    {
        try {
            Log::info('MyHealthController@checkRecordByIc: fetching check records', ['ic' => $ic]);

            $records = $this->myHealthService->getAllCheckRecordsByIC($ic);

            if ($records->isEmpty()) {
                Log::warning('MyHealthController@checkRecordByIc: no check records found', ['ic' => $ic]);

                return response()->json([
                    'success' => false,
                    'message' => 'No check record found for this IC',
                ], 404);
            }

            $recordIds = $records->pluck('id')->all();
            $detailsByRecord = $this->myHealthService->getRecordDetailsBatch($recordIds);

            $parameters = [];
            foreach ($records as $record) {
                $details = $detailsByRecord->get($record->id, collect());

                foreach ($details as $detail) {
                    $parameters[$detail->parameter] = [
                        'value' => $detail->result,
                        'range' => $detail->range,
                        'unit' => $detail->unit,
                    ];
                }
            }

            Log::info('MyHealthController@checkRecordByIc: completed', [
                'ic' => $ic,
                'record_count' => $records->count(),
                'parameter_count' => count($parameters),
            ]);

            return response()->json([
                'success' => true,
                'data' => $parameters,
            ], 200);
        } catch (Throwable $e) {
            Log::error('MyHealthController@checkRecordByIc: failed', [
                'ic' => $ic,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve check record',
                'error' => 'Internal server error',
            ], 500);
        }
    }
}

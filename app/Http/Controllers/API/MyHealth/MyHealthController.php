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
     * GET /api/myhealth/check-record/{ic}
     * All check_record data for an IC, across every visit (no date
     * restriction). Returns every parameter and every reading, keyed by
     * parameter name -> array of readings (newest first). Each reading:
     * {value, range, min_range, max_range, unit, date_time, record_id}.
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
            $detailsByParameter = $this->myHealthService->getAllRecordDetailsBatch($recordIds);

            $parameters = [];
            $readingCount = 0;
            foreach ($detailsByParameter as $parameterName => $rows) {
                $parameters[$parameterName] = $rows->map(function ($row) {
                    return [
                        'value' => $row->result,
                        'range' => $row->range,
                        'min_range' => $row->min_range,
                        'max_range' => $row->max_range,
                        'unit' => $row->unit,
                        'date_time' => $row->date_time,
                        'record_id' => $row->record_id,
                    ];
                })->values()->all();

                $readingCount += count($parameters[$parameterName]);
            }

            Log::info('MyHealthController@checkRecordByIc: completed', [
                'ic' => $ic,
                'record_count' => $records->count(),
                'parameter_count' => count($parameters),
                'reading_count' => $readingCount,
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

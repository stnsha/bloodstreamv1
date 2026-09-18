<?php

namespace App\Services;

use App\Models\TestResult;
use Carbon\Carbon;

class MyHealthMatcherService
{
    /**
     * Attach MyHealth check_record readings to each test result: every
     * reading taken within 14 days of the test result's collected_date
     * (either direction), grouped by parameter name.
     *
     * @param  \Illuminate\Support\Collection<int, TestResult>  $testResults
     * @param  \Illuminate\Support\Collection  $detailsByParameter  Rows grouped by parameter, as returned by MyHealthService::getAllRecordDetailsBatch()
     * @return array<int, array<string, array>> Keyed by test_result id -> parameter name -> readings
     */
    public function matchByCollectedDate($testResults, $detailsByParameter): array
    {
        $rows = collect();

        foreach ($detailsByParameter as $parameterName => $paramRows) {
            foreach ($paramRows as $row) {
                $rows->push((object) [
                    'parameter' => $parameterName,
                    'date_time' => $row->date_time,
                    'record_id' => $row->record_id,
                    'result' => $row->result,
                    'range' => $row->range,
                    'min_range' => $row->min_range,
                    'max_range' => $row->max_range,
                    'unit' => $row->unit,
                ]);
            }
        }

        $result = [];

        foreach ($testResults as $testResult) {
            if (! $testResult->collected_date) {
                $result[$testResult->id] = [];

                continue;
            }

            $collected = Carbon::parse($testResult->collected_date);

            $matched = $rows->filter(function ($row) use ($collected) {
                return abs(Carbon::parse($row->date_time)->diffInDays($collected)) <= 14;
            });

            $grouped = [];

            foreach ($matched->groupBy('parameter') as $parameterName => $paramRows) {
                $grouped[$parameterName] = $paramRows->map(function ($row) {
                    return [
                        'value' => $row->result,
                        'range' => $row->range,
                        'min_range' => $row->min_range,
                        'max_range' => $row->max_range,
                        'unit' => $row->unit,
                        'record_id' => $row->record_id,
                        'date_time' => $row->date_time,
                    ];
                })->values()->all();
            }

            $result[$testResult->id] = $grouped;
        }

        return $result;
    }
}

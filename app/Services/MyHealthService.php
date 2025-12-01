<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MyHealthService
{
    protected $connection;

    public function __construct()
    {
        $this->connection = DB::connection('mysql2');
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function query($sql, $bindings = [])
    {
        return $this->connection->select($sql, $bindings);
    }

    public function getCheckRecordIdByIC($ic)
    {
        $fourteenDaysAgo = now()->subDays(14)->format('Y-m-d H:i:s');

        return $this->connection->table('check_record')
            ->where('ic', $ic)
            ->where('date_time', '>=', $fourteenDaysAgo)
            ->select('id', 'gender', 'date_time')
            ->orderBy('date_time', 'desc')
            ->first();
    }

    public function getRecordDetailsByRecordId($recordId)
    {
        return $this->connection->table('check_record_details as d')
        ->join('check_normal_range as r', 'r.id', '=', 'd.parameter')
        ->where('d.record_id', $recordId)
        ->select(
            'r.parameter',
            'r.lower as min_range',
            'r.upper as max_range',
            'r.range',
            'r.unit',
            'd.value as result'
        )->get();
    }

    /**
     * Batch load record details for multiple record IDs to avoid N+1 queries
     * PERFORMANCE OPTIMIZATION
     *
     * @param array $recordIds Array of record IDs
     * @return \Illuminate\Support\Collection Collection grouped by record_id
     */
    public function getRecordDetailsBatch(array $recordIds)
    {
        if (empty($recordIds)) {
            return collect([]);
        }

        return $this->connection->table('check_record_details as d')
            ->join('check_normal_range as r', 'r.id', '=', 'd.parameter')
            ->whereIn('d.record_id', $recordIds)
            ->select(
                'd.record_id',
                'r.parameter',
                'r.lower as min_range',
                'r.upper as max_range',
                'r.range',
                'r.unit',
                'd.value as result'
            )
            ->get()
            ->groupBy('record_id');
    }

    /**
     * check_normal_range:
     * BMI ID: 55
     * BP ID: 10
     *
     * @param array $recordICs Array of IC numbers
     * @return array Associative array (IC => boolean) indicating if vitals are filled within last 14 days
     */
    public function isFilledVitals(array $recordICs)
    {
        if (empty($recordICs)) {
            return [];
        }

        // Initialize all ICs as false
        $result = array_fill_keys($recordICs, false);

        // Get ICs that have BOTH parameters (55 and 10) within the last 14 days
        $fourteenDaysAgo = now()->subDays(14)->format('Y-m-d H:i:s');

        $validICs = $this->connection->table('check_record as c')
            ->join('check_record_details as d', 'c.id', '=', 'd.record_id')
            ->whereIn('c.ic', $recordICs)
            ->where('c.date_time', '>=', $fourteenDaysAgo)
            ->whereIn('d.parameter', [55, 10])
            ->select('c.ic', DB::raw('COUNT(DISTINCT d.parameter) as param_count'))
            ->groupBy('c.ic')
            ->havingRaw('COUNT(DISTINCT d.parameter) = 2')
            ->pluck('ic')
            ->toArray();

        // Set true for ICs that meet all criteria
        foreach ($validICs as $ic) {
            $result[$ic] = true;
        }

        return $result;
    }
}
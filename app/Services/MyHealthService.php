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
        return $this->connection->table('check_record')
            ->where('ic', $ic)
            ->whereYear('date_time', date('Y'))
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

}
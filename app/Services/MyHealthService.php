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
            ->select('id', 'gender', 'date_time')
            ->get();
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
   
}
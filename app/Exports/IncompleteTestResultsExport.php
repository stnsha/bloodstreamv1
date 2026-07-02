<?php

namespace App\Exports;

use App\Models\IncompleteTestResult;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class IncompleteTestResultsExport implements FromQuery, WithHeadings, WithMapping
{
    public function query(): Builder
    {
        return IncompleteTestResult::query()
            ->join('test_results', 'test_results.id', '=', 'incomplete_test_results.test_result_id')
            ->whereNull('test_results.deleted_at')
            ->select('test_results.ref_id', 'test_results.lab_no')
            ->orderBy('incomplete_test_results.id');
    }

    public function headings(): array
    {
        return ['ref_id', 'lab_no'];
    }

    public function map($row): array
    {
        return [
            $row->ref_id ?? '',
            $row->lab_no,
        ];
    }
}

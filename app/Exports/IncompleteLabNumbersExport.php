<?php

namespace App\Exports;

use App\Exports\Sheets\IncompleteLabListSheet;
use App\Exports\Sheets\IncompleteLabSummarySheet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class IncompleteLabNumbersExport implements WithMultipleSheets, ShouldQueue
{
    use Exportable;

    public function sheets(): array
    {
        return [
            new IncompleteLabListSheet(),
            new IncompleteLabSummarySheet(),
        ];
    }
}

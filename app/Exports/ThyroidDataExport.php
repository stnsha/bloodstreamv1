<?php

namespace App\Exports;

use App\Services\ThyroidExportService;
use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ThyroidDataExport implements FromGenerator, WithHeadings, WithColumnWidths
{
    private ThyroidExportService $service;
    private string $dateFrom;
    private string $dateTo;
    private ?int $limit;
    private $onProgress;

    public function __construct(ThyroidExportService $service, string $dateFrom, string $dateTo, ?int $limit = null, ?callable $onProgress = null)
    {
        $this->service    = $service;
        $this->dateFrom   = $dateFrom;
        $this->dateTo     = $dateTo;
        $this->limit      = $limit;
        $this->onProgress = $onProgress;
    }

    public function generator(): Generator
    {
        return $this->service->rowGenerator($this->dateFrom, $this->dateTo, $this->limit, $this->onProgress);
    }

    public function headings(): array
    {
        return [
            'Lab No',
            'Ref ID',
            'Collected Date',
            'Age',
            'Gender',
            'Race',
            'Regional',
            'Outlet',
            'TSH Value',
            'TSH Reference Range',
            'FT4 Value',
            'FT4 Reference Range',
            'FT3 Value',
            'FT3 Reference Range',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 15,
            'C' => 20,
            'D' => 8,
            'E' => 10,
            'F' => 14,
            'G' => 26,
            'H' => 32,
            'I' => 12,
            'J' => 22,
            'K' => 12,
            'L' => 22,
            'M' => 12,
            'N' => 22,
        ];
    }
}

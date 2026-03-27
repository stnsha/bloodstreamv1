<?php

namespace App\Exports\Sheets;

use App\Models\TestResult;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IncompleteLabListSheet implements FromQuery, WithTitle, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithCustomChunkSize
{
    public function title(): string
    {
        return 'Lab Numbers';
    }

    public function query()
    {
        return TestResult::select('lab_no', 'collected_date', 'updated_at')
            ->where('is_completed', 0)
            ->where('is_reviewed', 0)
            ->orderBy('created_at', 'desc');
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function headings(): array
    {
        return [
            'Lab Number',
            'Collected At',
            'Last Updated',
        ];
    }

    public function map($row): array
    {
        return [
            $row->lab_no,
            $row->collected_date ? $row->collected_date->format('Y-m-d H:i:s') : null,
            $row->updated_at ? $row->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ],
            'A:C' => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 22,
            'C' => 22,
        ];
    }
}

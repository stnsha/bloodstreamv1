<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IncompleteLabSummarySheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function title(): string
    {
        return 'Summary';
    }

    public function array(): array
    {
        $rows = DB::select(
            'SELECT YEAR(created_at) AS year, MONTH(created_at) AS month, COUNT(*) AS total
             FROM test_results
             WHERE is_completed = 0 AND is_reviewed = 0
             GROUP BY YEAR(created_at), MONTH(created_at)
             ORDER BY year, month'
        );

        return array_map(fn ($row) => [
            $row->year,
            $row->month,
            $row->total,
        ], $rows);
    }

    public function headings(): array
    {
        return [
            'Year',
            'Month',
            'Total',
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
            'A' => 10,
            'B' => 10,
            'C' => 12,
        ];
    }
}

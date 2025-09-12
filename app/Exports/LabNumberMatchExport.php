<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LabNumberMatchExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    protected $matchResults;
    protected $summary;

    public function __construct(array $matchResults, array $summary)
    {
        $this->matchResults = $matchResults;
        $this->summary = $summary;
    }

    public function array(): array
    {
        $data = [];

        // Add summary rows at the top
        $data[] = ['SUMMARY', '', '', ''];
        $data[] = ['Total Lab Numbers:', $this->summary['total_lab_numbers'] ?? 0, '', ''];
        $data[] = ['Exist in Database:', $this->summary['exist_in_db'] ?? 0, '', ''];
        $data[] = ['Not Exist in Database:', $this->summary['not_exist_in_db'] ?? 0, '', ''];
        $data[] = ['', '', '', ''];
        $data[] = ['DETAILS', '', '', ''];

        // Process match results
        foreach ($this->matchResults as $result) {
            $fullLabNumber = $result['from_excel'];
            
            // Try to split prefix and lab number
            if (strpos($fullLabNumber, '-') !== false) {
                [$prefix, $labNumber] = explode('-', $fullLabNumber, 2);
            } else {
                $prefix = '';
                $labNumber = $fullLabNumber;
            }

            $data[] = [
                $prefix,
                $labNumber,
                $result['from_excel'],
                $result['is_exist'] ? 'EXISTS' : 'NOT FOUND'
            ];
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'Prefix',
            'Lab Number', 
            'Full Lab Number in Blood Stream API',
            'Status'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->matchResults) + 7; // 6 summary rows + 1 header row
        
        return [
            // Summary section styling
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['bold' => true]],
            3 => ['font' => ['bold' => true]],
            4 => ['font' => ['bold' => true]],
            6 => ['font' => ['bold' => true, 'size' => 12]],
            
            // Header row styling
            7 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0']
                ]
            ],
            
            // Status column conditional formatting would need to be done differently
            // For now, we'll style the entire range
            "A1:D{$lastRow}" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // Prefix
            'B' => 20, // Lab Number
            'C' => 30, // Full Lab Number
            'D' => 15, // Status
        ];
    }
}
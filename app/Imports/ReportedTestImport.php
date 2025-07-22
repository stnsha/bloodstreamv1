<?php

namespace App\Imports;

use App\Models\Doctor;
use App\Models\Panel;
use App\Models\PanelItem;
use App\Models\PanelMetadata;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ReportedTestImport implements ToArray, WithHeadingRow
{
    public function array(array $array)
    {
        $processedData = [];
        foreach ($array as $row) {
            $processedData[] = [
                'panel_code' => $row['panel'],
                'name' => $row['name_1'],
                'result_type' => $row['result_type'],
                'unit' => $row['units'],
            ];
        }

        $this->store($processedData);
    }

    public function store(array $processedData)
    {
        $panelItems = [];
        foreach ($processedData as $data) {
            $panel = Panel::where('code', $data['panel_code'])->first();

            if (!empty($panel)) {
                $panel_id = $panel->id;
                PanelItem::firstOrCreate(
                    [
                        'panel_id' => $panel_id,
                        'name' => $data['name'],
                    ],
                    [
                        'unit' => $data['unit'],
                        'result_type' => $data['result_type'],
                    ]
                );
            }
        }
    }
}

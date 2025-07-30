<?php

namespace App\Imports;

use App\Models\Panel;
use App\Models\PanelItem;
use App\Models\PanelMetadata;
use App\Models\PanelTag;
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
                'panel_name' => $row['name'],
                'item' => $row['item'],
                'name' => $row['name_1'],
                'external_item' => $row['external_item'],
                'result_type' => $row['result_type'],
                'unit' => $row['units'],
            ];
        }

        $this->store($processedData);
    }

    public function store(array $processedData)
    {
        foreach ($processedData as $data) {
            $panel = Panel::where('code', $data['panel_code'])->first();
            if (!empty($panel)) {
                $panel_id = $panel->id;
            } else {
                $panelTag = PanelTag::where('code', $data['panel_code'])->first();
                if (!empty($panelTag)) {
                    $panel_id = $panelTag->panel_id;
                } else {
                    $panel = Panel::firstOrCreate([
                        'lab_id' => 2, // Set appropriate lab_id
                        'code' => $data['panel_code']
                    ], [
                        'panel_category_id' => null,
                        'name' => $data['panel_name'],
                    ]);

                    $panel_id = $panel->id;
                }
            }

            PanelItem::firstOrCreate(
                [
                    'panel_id' => $panel_id,
                    'name' => $data['name'],
                    'identifier' => $data['external_item'],
                ],
                [
                    'unit' => $data['unit'],
                    'result_type' => $data['result_type'],
                    'code' => $data['item']
                ]
            );
        }
    }
}

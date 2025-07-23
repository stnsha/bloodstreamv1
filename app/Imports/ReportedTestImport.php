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
                $panel_tag_id = null;
            } else {
                $panelTag = PanelTag::where('code', $data['panel_code'])->first();
                if (!empty($panelTag)) {
                    $panel_tag_id = $panelTag->id;
                    $panel_id = $panelTag->panel_id;
                } else {
                    continue;
                }
            }

            $panel_item = PanelItem::firstOrCreate(
                [
                    'panel_id' => $panel_id,
                    'panel_tag_id' => $panel_tag_id,
                    'name' => $data['name'],
                ],
                [
                    'unit' => $data['unit'],
                    'result_type' => $data['result_type'],
                ]
            );

            $panel_item_id = $panel_item->id;

            PanelMetadata::firstOrCreate(
                [
                    'panel_item_id' => $panel_item_id,
                    'identifier' => $data['external_item'],
                ],
                [
                    'code' => $data['item']
                ]
            );
        }
    }
}

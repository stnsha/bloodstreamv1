<?php

namespace App\Imports;

use App\Models\Panel;
use App\Models\PanelTag;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TagOnImport implements ToArray, WithHeadingRow
{
    public function array(array $array)
    {
        $processedData = [];
        foreach ($array as $row) {
            $processedData[] = [
                'panel_code' => trim($row['panel_code']),
                'panel_name' => trim($row['panel_name']),
                'code' => trim($row['tag_on_code']),
                'name' => trim($row['tag_on_name']),
            ];
        }

        $this->store($processedData);
    }

    public function store(array $processedData)
    {
        foreach ($processedData as $data) {
            $panel = Panel::where('lab_id', 2)
                ->where('code', trim($data['panel_code']))
                ->first();
            if (!$panel) {
                $panel = Panel::create([
                    'lab_id' => 2,
                    'code' => trim($data['panel_code']),
                    'name' => trim($data['panel_name']),
                ]);
            }

            PanelTag::create([
                'panel_id' => $panel->id,
                'code' => trim($data['code']),
                'name' => trim($data['name']),
            ]);
        }
    }
}

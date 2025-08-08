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
                'panel_code' => trimOrNull($row['panel_code']),
                'panel_name' => trimOrNull($row['panel_name']),
                'code' => trimOrNull($row['tag_on_code']),
                'name' => trimOrNull($row['tag_on_name']),
            ];
        }

        $this->store($processedData);
    }

    public function store(array $processedData)
    {
        foreach ($processedData as $data) {
            $panel = Panel::where('lab_id', 2)
                ->where('code', $data['panel_code'])
                ->first();
            if (!$panel) {
                $panel = Panel::create([
                    'lab_id' => 2,
                    'code' => $data['panel_code'],
                    'name' => $data['panel_name'],
                ]);
            }

            PanelTag::create([
                'lab_id' => 2,
                'panel_id' => $panel->id,
                'code' => $data['code'],
                'name' => $data['name'],
            ]);
        }
    }
}

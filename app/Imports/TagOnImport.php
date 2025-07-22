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
                'panel_code' => $row['panel_code'],
                'code' => $row['tag_on_code'],
                'name' => $row['tag_on_name'],
            ];
        }

        $this->store($processedData);
    }

    public function store(array $processedData)
    {
        foreach ($processedData as $data) {
            $panel = Panel::where('code', $data['panel_code'])->first();
            if (!$panel) {
                continue;
            }

            PanelTag::create([
                'panel_id' => $panel->id,
                'code' => $data['code'],
                'name' => $data['name'],
            ]);
        }
    }
}

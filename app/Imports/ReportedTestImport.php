<?php

namespace App\Imports;

use App\Models\Panel;
use App\Models\PanelItem;
use App\Models\PanelTag;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Str;

class ReportedTestImport implements ToArray, WithHeadingRow
{
    public function array(array $array)
    {
        $processedData = [];
        foreach ($array as $row) {
            $processedData[] = [
                'panel_code' => trim($row['panel']),
                'panel_name' => trim($row['name']),
                'panel_item_code' => trim($row['item']),
                'panel_item_name' => trim($row['name_1']),
                'panel_item_identifier' => trim($row['external_item']),
                'result_type' => trim($row['result_type']),
                'unit' => trim($row['units']),
            ];
        }

        $this->store($processedData);
    }

    public function store(array $processedData)
    {
        foreach ($processedData as $data) {
            if (!str_contains($data['panel_code'], 'QON') && !str_contains($data['panel_code'], 'TON')) {
                $panel_id = null;

                // Check if this is a TAG ON item
                $isTagOn = $this->isTagOnItem($data['panel_name']);

                //If true
                if ($isTagOn) {
                    //Search panel tag
                    $panelTag = PanelTag::where('code', $data['panel_code'])->first();
                    //If not found
                    if (!$panelTag) {
                        //remove word tag on on both names to search in panel 
                        $tempPanelName = $this->extractBasePanelName($data['panel_name']);
                        $tempPIName = $this->extractBasePanelName($data['panel_item_name']);

                        //Search panel by name
                        $isPanelExist = Panel::whereIn('name', [$tempPanelName, $tempPIName])->first();

                        //If found
                        if ($isPanelExist) {
                            $panel_id = $isPanelExist->id;
                        }

                        PanelTag::create([
                            'panel_id' => $panel_id,
                            'name' => $data['panel_name'],
                            'code' => $data['panel_code'],
                        ]);
                    }
                }
            }
        }
    }

    private function isTagOnItem($panelName)
    {
        $tagOnKeywords = ['TAG ON', 'TAGON', 'TAG-ON'];
        foreach ($tagOnKeywords as $keyword) {
            if (Str::contains(strtoupper($panelName), $keyword)) {
                return true;
            }
        }
        return false;
    }

    private function extractBasePanelName($panelName)
    {
        // Remove TAG ON related keywords and clean up
        $baseName = preg_replace('/\s*\(?\s*(TAG[\s\-]?ON)\s*\)?/i', '', trim($panelName));
        $baseName = preg_replace('/\s*TAGON\s*/i', '', $baseName);

        return trim($baseName);
    }
}

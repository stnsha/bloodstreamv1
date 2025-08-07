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
                'panel_code' => trimOrNull($row['panel']),
                'panel_name' => trimOrNull($row['name']),
                'panel_item_code' => trimOrNull($row['item']),
                'panel_item_name' => trimOrNull($row['name_1']),
                'panel_item_identifier' => trimOrNull($row['external_item']),
                'result_type' => trimOrNull($row['result_type']),
                'unit' => trimOrNull($row['units']),
            ];
        }

        $this->store($processedData);
    }

    public function store(array $processedData)
    {
        foreach ($processedData as $data) {
            if (!str_contains($data['panel_code'], 'QON') && !str_contains($data['panel_code'], 'TON')) {
                // Check if this is a TAG ON item
                $isTagOn = $this->isTagOnItem($data['panel_name']);
                //If true
                if ($isTagOn) {
                    $this->storePanelTag($data);
                } else {
                    $this->storePanelItem($data);
                }
            }
        }
    }

    private function storePanelItem(array $data)
    {
        $panel = Panel::where('code', $data['panel_code'])->first();
        if ($panel) {
            // Find or create the PanelItem
            $panelItem = PanelItem::firstOrCreate(
                [
                    'code' => $data['panel_item_code'],
                ],
                [
                    'name' => $data['panel_item_name'],
                    'identifier' => $data['panel_item_identifier'],
                    'result_type' => $data['result_type'],
                    'unit' => $data['unit'],
                ]
            );
            
            // Attach the panel to this panel item (many-to-many)
            $panel->panelItems()->syncWithoutDetaching([$panelItem->id]);
        }
    }

    private function storePanelTag(array $data)
    {
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

                PanelTag::create([
                    'panel_id' => $panel_id,
                    'name' => $data['panel_name'],
                    'code' => $data['panel_code'],
                ]);
            }
        }
    }

    private function isTagOnItem($panelName)
    {
        // Handle case where panelName might be an array or null
        $trimmed = trimOrNull($panelName);
        if (!$trimmed) {
            return false;
        }

        $tagOnKeywords = ['TAG ON', 'TAGON', 'TAG-ON'];
        foreach ($tagOnKeywords as $keyword) {
            if (Str::contains(strtoupper($trimmed), $keyword)) {
                return true;
            }
        }
        return false;
    }

    private function extractBasePanelName($panelName)
    {
        // Handle case where panelName might be an array or null
        $trimmed = trimOrNull($panelName);
        if (!$trimmed) {
            return '';
        }

        // Remove TAG ON related keywords and clean up
        $baseName = preg_replace('/\s*\(?\s*(TAG[\s\-]?ON)\s*\)?/i', '', $trimmed);
        $baseName = preg_replace('/\s*TAGON\s*/i', '', $baseName);

        return trimOrNull($baseName) ?: '';
    }
}

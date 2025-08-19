<?php

namespace App\Imports;

use App\Models\Panel;
use App\Models\PanelItem;
use App\Models\PanelTag;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReportedTestImport extends BaseCodeMappingImport
{
    protected function processRow(array $row): ?array
    {
        // Skip rows with missing essential data
        if (!$this->hasEssentialData($row)) {
            return null;
        }


        return [
            'panel_code' => $this->trimOrNull($row['panel'] ?? null),
            'panel_name' => $this->trimOrNull($row['name'] ?? null),
            'int_code' => $this->trimOrNull($row['master_format'] ?? null),
            'panel_item_code' => $this->trimOrNull($row['item'] ?? null),
            'panel_item_name' => $this->trimOrNull($row['name_1'] ?? null),
            'panel_item_identifier' => $this->trimOrNull($row['external_item'] ?? null),
            'result_type' => $this->trimOrNull($row['result_type'] ?? null),
            'unit' => $this->trimOrNull($row['units'] ?? null),
        ];
    }

    /**
     * Check if row has essential data for Reported Test import
     */
    protected function hasEssentialData(array $row): bool
    {
        // First check if the row is completely empty
        if ($this->isEmptyRow($row)) {
            return false;
        }

        // Reported Test import requires panel code and item code
        $panelCode = $this->trimOrNull($row['panel'] ?? null);
        $itemCode = $this->trimOrNull($row['item'] ?? null);

        return !empty($panelCode) && !empty($itemCode);
    }

    protected function store(array $processedData): void
    {
        foreach ($processedData as $data) {
            if (!str_contains($data['panel_code'], 'QON') && !str_contains($data['panel_code'], 'TON')) {
                // Check if this is a TAG ON item
                $isTagOn = $this->isTagOnItem($data['panel_name'] ?? null, $data['master_format'] ?? null);
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
        // Always use updateOrCreate to ensure int_code is updated
        $panel = Panel::updateOrCreate(
            [
                'lab_id' => $this->labId,
                'code' => $data['panel_code'],
            ],
            [
                'name' => $data['panel_name'],
                'int_code' => $data['int_code'],
                // 'overall_notes' => 'ReportedTestImport'
            ]
        );
        $this->trackDatabaseOperation('create', $panel->wasRecentlyCreated);

        // Find or create the PanelItem
        $panelItem = PanelItem::firstOrCreate(
            [
                'lab_id' => $this->labId,
                'code' => $data['panel_item_code'],
            ],
            [
                'name' => $data['panel_item_name'],
                'identifier' => $data['panel_item_identifier'],
                'result_type' => $data['result_type'],
                'unit' => $data['unit'],
            ]
        );
        $this->trackDatabaseOperation('create', $panelItem->wasRecentlyCreated);

        // Attach the panel to this panel item (many-to-many)
        $panel->panelItemsSync()->syncWithoutDetaching([$panelItem->id]);
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

                $panelTag = PanelTag::create([
                    'lab_id' => $this->labId,
                    'panel_id' => $panel_id,
                    'name' => $data['panel_name'],
                    'code' => $data['panel_code'],
                ]);
                $this->trackDatabaseOperation('create', true);
            }
        }
    }

    private function isTagOnItem($panelName = null, $masterFormat = null)
    {
        // Check if master_format is TGA
        if ($masterFormat && strtoupper(trim($masterFormat)) === 'TGA') {
            return true;
        }

        // Handle case where panelName might be an array or null
        $trimmed = $this->trimOrNull($panelName);
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
        $trimmed = $this->trimOrNull($panelName);
        if (!$trimmed) {
            return '';
        }

        // Remove TAG ON related keywords and clean up
        $baseName = preg_replace('/\s*\(?\s*(TAG[\s\-]?ON)\s*\)?/i', '', $trimmed);
        $baseName = preg_replace('/\s*TAGON\s*/i', '', $baseName);

        return $this->trimOrNull($baseName) ?: '';
    }

    public function rules(): array
    {
        return [
            '*.panel' => 'nullable|string',
            '*.item' => 'nullable|string',
        ];
    }
}
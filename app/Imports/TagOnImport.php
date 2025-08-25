<?php

namespace App\Imports;

use App\Models\Panel;
use App\Models\PanelItem;
use App\Models\MasterPanel;
use App\Models\MasterPanelItem;
use App\Models\PanelTag;

class TagOnImport extends BaseCodeMappingImport
{
    protected function processRow(array $row): ?array
    {
        // Skip rows with missing essential data
        if (!$this->hasEssentialData($row)) {
            return null;
        }
        return [
            'panel_code' => $this->trimOrNull($row['panel_code']),
            'panel_name' => $this->trimOrNull($row['panel_name']),
            'tagon_code' => $this->trimOrNull($row['tag_on_code']),
            'tagon_name' => $this->trimOrNull($row['tag_on_name']),
        ];
    }

    /**
     * Check if row has essential data for Tag On import
     */
    protected function hasEssentialData(array $row): bool
    {
        // First check if the row is completely empty
        if ($this->isEmptyRow($row)) {
            return false;
        }

        // Tag On import requires panel code and tag on name
        $panelCode = $this->trimOrNull($row['panel_code'] ?? null);
        $tagOnName = $this->trimOrNull($row['tag_on_name'] ?? null);

        return !empty($panelCode) && !empty($tagOnName);
    }

    protected function store(array $processedData): void
    {
        foreach ($processedData as $data) {
            // 1. First, create or find master panel
            $masterPanel = MasterPanel::firstOrCreate([
                'name' => $data['panel_name']
            ]);
            $this->trackDatabaseOperation('create', $masterPanel->wasRecentlyCreated);

            // 2. Create or get Panel with master panel referencea
            $panel = Panel::firstOrCreate(
                [
                    'lab_id' => $this->labId,
                    'master_panel_id' => $masterPanel->id,
                ],
                [
                    'name' => $data['panel_name'],
                    'code' => $data['panel_code']
                ]
            );

            $this->trackDatabaseOperation('create', $panel->wasRecentlyCreated);

            // 3. Create or find tag on
            $tagOn = PanelTag::firstOrCreate(
                [
                    'panel_id' => $panel->id,
                    'code' => $data['tagon_code'],
                ],
                [
                    'lab_id' => $this->labId,
                    'name' => $data['tagon_name'],
                ]
            );

            $this->trackDatabaseOperation('create', $tagOn->wasRecentlyCreated);
        }
    }

    public function rules(): array
    {
        return [
            '*.panel_code' => 'nullable|string',
            '*.tag_on_name' => 'nullable|string',
        ];
    }
}
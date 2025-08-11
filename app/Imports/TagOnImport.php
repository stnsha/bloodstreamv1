<?php

namespace App\Imports;

use App\Models\Panel;
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
            'code' => $this->trimOrNull($row['tag_on_code']),
            'name' => $this->trimOrNull($row['tag_on_name']),
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

        // Tag On import requires panel code and tag on code
        $panelCode = $this->trimOrNull($row['panel_code'] ?? null);
        $tagOnCode = $this->trimOrNull($row['tag_on_code'] ?? null);
        
        return !empty($panelCode) && !empty($tagOnCode);
    }

    protected function store(array $processedData): void
    {
        foreach ($processedData as $data) {
            $panel = Panel::where('lab_id', $this->labId)
                ->where('code', $data['panel_code'])
                ->first();
                
            if (!$panel) {
                $panel = Panel::create([
                    'lab_id' => $this->labId,
                    'code' => $data['panel_code'],
                    'name' => $data['panel_name'],
                ]);
                $this->trackDatabaseOperation('create', true);
            }

            $panelTag = PanelTag::firstOrCreate([
                'lab_id' => $this->labId,
                'panel_id' => $panel->id,
                'code' => $data['code'],
            ], [
                'name' => $data['name'],
            ]);
            $this->trackDatabaseOperation('create', $panelTag->wasRecentlyCreated);
        }
    }

    public function rules(): array
    {
        return [
            '*.panel_code' => 'nullable|string',
            '*.tag_on_code' => 'nullable|string',
        ];
    }
}

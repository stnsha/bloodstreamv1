<?php

namespace App\Imports;

use App\Models\MasterPanel;
use App\Models\MasterPanelItem;
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

    /**
     * Get specific reason why a row was skipped
     */
    protected function getSkipReason(array $row): string
    {
        // Check for missing essential fields
        $panelCode = $this->trimOrNull($row['panel'] ?? null);
        $itemCode = $this->trimOrNull($row['item'] ?? null);
        $panelName = $this->trimOrNull($row['name'] ?? null);
        $itemName = $this->trimOrNull($row['name_1'] ?? null);

        $missingFields = [];

        if (empty($panelCode)) {
            $missingFields[] = 'panel_code';
        }

        if (empty($itemCode)) {
            $missingFields[] = 'item_code';
        }

        if (empty($panelName)) {
            $missingFields[] = 'panel_name';
        }

        if (empty($itemName)) {
            $missingFields[] = 'item_name';
        }

        if (!empty($missingFields)) {
            return 'Missing required fields: ' . implode(', ', $missingFields);
        }

        // Check for QON/TON exclusions
        if (str_contains($panelCode, 'QON') || str_contains($panelCode, 'TON')) {
            return 'Excluded panel code (contains QON/TON)';
        }

        // If we reach here, it's a generic processing failure
        return 'Failed processing requirements or validation';
    }

    protected function store(array $processedData): void
    {
        foreach ($processedData as $data) {
            // Skip rows with "comment" in panel item name (case-insensitive)
            $panelItemName = $data['panel_item_name'] ?? '';
            if (!empty($panelItemName) && str_contains(strtolower($panelItemName), 'comment')) {
                $this->trackCommentSkip($data, "Panel item name contains 'comment': {$panelItemName}");
                continue;
            }

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
        // Find or create MasterPanel
        $masterPanel = MasterPanel::firstOrCreate(
            [
                'name' => $data['panel_name'],
            ]
        );

        // Always use updateOrCreate to ensure int_code is updated
        $panel = Panel::updateOrCreate(
            [
                'master_panel_id' => $masterPanel->id,
                'lab_id' => $this->labId,
                'code' => $data['panel_code'],
            ],
            [
                'int_code' => $data['int_code'],
                // 'overall_notes' => 'ReportedTestImport'
            ]
        );
        $this->trackDatabaseOperation('create', $panel->wasRecentlyCreated);

        //Find or create MasterPanelItem
        $masterPanelItem = MasterPanelItem::firstOrCreate(
            [
                'name' => $data['panel_item_name'],
                'unit' => $data['unit'],
            ]
        );

        // Find or create the PanelItem
        $panelItem = PanelItem::firstOrCreate(
            [
                'lab_id' => $this->labId,
                'master_panel_item_id' => $masterPanelItem->id,
            ],
            [
                'code' => null,
                'identifier' => $data['panel_item_identifier'],
            ]
        );
        $this->trackDatabaseOperation('create', $panelItem->wasRecentlyCreated);

        // Attach the panel to this panel item (many-to-many)
        $panel->panelItems()->syncWithoutDetaching([$panelItem->id]);
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
            $isPanelExist = MasterPanel::whereIn('name', [$tempPanelName, $tempPIName])->first();

            //If found
            if ($isPanelExist) {
                $panel = Panel::where('master_panel_id', $isPanelExist->id)
                    ->where('lab_id', $this->labId)
                    ->first();

                if ($panel) {
                    $panelTag = PanelTag::firstOrCreate(
                        [
                            'panel_id' => $panel->id,
                            'code' => $data['panel_code'],
                        ],
                        [
                            'name' => $data['panel_name'],
                            'lab_id' => $this->labId,
                        ]
                    );
                    $this->trackDatabaseOperation('create', true);
                } else {
                    Log::warning('Panel not found for panel tag import', [
                        'master_panel_id' => $isPanelExist->id,
                        'lab_id' => $this->labId,
                        'panel_code' => $data['panel_code']
                    ]);
                }
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
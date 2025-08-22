<?php

namespace App\Imports;

use App\Models\PanelProfile;
use App\Models\PanelCategory;
use App\Models\Panel;
use App\Models\MasterPanel;

class ProfileCodeImport extends BaseCodeMappingImport
{
    protected function processRow(array $row): ?array
    {
        // Skip rows with missing essential data
        if (!$this->hasEssentialData($row)) {
            return null;
        }

        return [
            'profile_code' => $this->trimOrNull($row['lis_profilepackage_code']), // LIS Profile/Package Code
            'profile_name' => $this->trimOrNull($row['lis_profile_test_name']), // LIS Profile Test Name
            'panel_code' => $this->trimOrNull($row['panel']), // Panel
            'panel_name' => $this->trimOrNull($row['panel_name']), // Panel Name
            'panel_category' => $this->trimOrNull($row['lab_category']), // Lab Category
            'remarks' => $this->trimOrNull($row['remarks']), // Remarks
        ];
    }

    /**
     * Check if row has essential data for Profile Code import
     */
    protected function hasEssentialData(array $row): bool
    {
        // First check if the row is completely empty
        if ($this->isEmptyRow($row)) {
            return false;
        }

        // Profile Code import requires panel code and panel name
        $panelCode = $this->trimOrNull($row['panel'] ?? null);
        $panelName = $this->trimOrNull($row['panel_name'] ?? null);

        return !empty($panelCode) && !empty($panelName);
    }

    protected function store(array $processedData): void
    {
        foreach ($processedData as $data) {
            // 1. Create or get PanelProfile
            $panelProfile = PanelProfile::firstOrCreate([
                'lab_id' => $this->labId,
                'code' => $data['profile_code']
            ], [
                'name' => $data['profile_name'],
                'code' => $data['profile_code']
            ]);
            $this->trackDatabaseOperation('create', $panelProfile->wasRecentlyCreated);

            // 2. Create or get PanelCategory
            $panelCategory = PanelCategory::firstOrCreate([
                'lab_id' => $this->labId,
                'name' => $data['panel_category']
            ], [
                'name' => $data['panel_category'],
                'code' => null
            ]);
            $this->trackDatabaseOperation('create', $panelCategory->wasRecentlyCreated);

            // 3. First, create or find master panel
            $masterPanel = MasterPanel::firstOrCreate([
                'name' => $data['panel_name']
            ]);
            $this->trackDatabaseOperation('create', $masterPanel->wasRecentlyCreated);

            // 4. Create or update Panel with master panel reference
            $panel = Panel::updateOrCreate([
                'lab_id' => $this->labId,
                'master_panel_id' => $masterPanel->id
            ], [
                'code' => $data['panel_code'],
                'int_code' => null,
                'sequence' => $data['remarks']
            ]);
            $this->trackDatabaseOperation('create', $panel->wasRecentlyCreated);
        }
    }

    public function rules(): array
    {
        return [
            '*.panel' => 'nullable|string',
            '*.panel_name' => 'nullable|string',
        ];
    }
}
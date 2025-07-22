<?php

namespace App\Imports;

use App\Models\PanelProfile;
use App\Models\PanelCategory;
use App\Models\Panel;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProfileCodeImport implements ToArray, WithHeadingRow
{
    public function array(array $array)
    {
        $processedData = [];

        foreach ($array as $row) {
            $processedData[] = [
                'profile_code' => $row['lis_profilepackage_code'], // LIS Profile/Package Code
                'profile_name' => $row['lis_profile_test_name'], // LIS Profile Test Name
                'panel_code' => $row['panel'], // Panel
                'panel_name' => $row['panel_name'], // Panel Name
                'panel_category' => $row['lab_category'], // Lab Category
                'remarks' => $row['remarks'], // Remarks
            ];
        }

        $this->store($processedData);
    }

    private function store(array $processedData)
    {
        $results = [];

        foreach ($processedData as $data) {
            // 1. Create or get PanelProfile
            $panelProfile = PanelProfile::firstOrCreate([
                'lab_id' => 2,
                'code' => $data['profile_code']
            ], [
                'name' => $data['profile_name'],
                'code' => $data['profile_code']
            ]);

            // 2. Create or get PanelCategory
            $panelCategory = PanelCategory::firstOrCreate([
                'lab_id' => 2,
                'panel_profile_id' => $panelProfile->id,
                'name' => $data['panel_category']
            ], [
                'panel_profile_id' => $panelProfile->id,
                'name' => $data['panel_category'],
                'code' => null
            ]);

            // 3. Create or get Panel
            $panel = Panel::firstOrCreate([
                'lab_id' => 2, // Set appropriate lab_id
                'panel_category_id' => $panelCategory->id,
                'code' => $data['panel_code']
            ], [
                'panel_category_id' => $panelCategory->id,
                'name' => $data['panel_name'],
                'code' => $data['panel_code'],
                'sequence' => null,
                'overall_notes' => $data['remarks']
            ]);

            $results[] = [
                'panel_profile' => $panelProfile,
                'panel_category' => $panelCategory,
                'panel' => $panel
            ];
        }

        return $results;
    }
}
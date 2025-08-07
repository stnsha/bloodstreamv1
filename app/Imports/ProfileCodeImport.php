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
                'profile_code' => trim($row['lis_profilepackage_code']), // LIS Profile/Package Code
                'profile_name' => trim($row['lis_profile_test_name']), // LIS Profile Test Name
                'panel_code' => trim($row['panel']), // Panel
                'panel_name' => trim($row['panel_name']), // Panel Name
                'panel_category' => trim($row['lab_category']), // Lab Category
                'remarks' => ($value = trim($row['remarks'])) === '' ? null : $value, // Remarks
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
                'code' => trim($data['profile_code'])
            ], [
                'name' => trim($data['profile_name']),
                'code' => trim($data['profile_code'])
            ]);

            // 2. Create or get PanelCategory
            $panelCategory = PanelCategory::firstOrCreate([
                'lab_id' => 2,
                'name' => trim($data['panel_category'])
            ], [
                'name' => trim($data['panel_category']),
                'code' => null
            ]);

            // 3. Create or update Panel
            $panel = Panel::updateOrCreate([
                'lab_id' => 2,
                'code' => trim($data['panel_code'])
            ], [
                'panel_category_id' => $panelCategory->id,
                'name' => trim($data['panel_name']),
                'sequence' => null,
                'overall_notes' => $data['remarks'] ? trim($data['remarks']) : null
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

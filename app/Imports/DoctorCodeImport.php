<?php

namespace App\Imports;

use App\Models\Doctor;

class DoctorCodeImport extends BaseCodeMappingImport
{
    protected function processRow(array $row): ?array
    {
        // Skip rows with missing essential data
        if (!$this->hasEssentialData($row)) {
            return null;
        }

        return [
            'type' => $this->trimOrNull($row['clinicpharmacy']),
            'code' => $this->trimOrNull($row['dr_code']),
            'name' => $this->trimOrNull($row['doctor_name']),
            'outlet_name' => $this->trimOrNull($row['outlet']),
            'outlet_address' => $this->trimOrNull($row['address'])
        ];
    }

    /**
     * Check if row has essential data for Doctor Code import
     */
    protected function hasEssentialData(array $row): bool
    {
        // First check if the row is completely empty
        if ($this->isEmptyRow($row)) {
            return false;
        }

        // Doctor Code import requires doctor code and doctor name
        $doctorCode = $this->trimOrNull($row['dr_code'] ?? null);
        $doctorName = $this->trimOrNull($row['doctor_name'] ?? null);

        return !empty($doctorCode) && !empty($doctorName);
    }

    protected function store(array $processedData): void
    {
        foreach ($processedData as $data) {
            $doctor = Doctor::firstOrCreate(
                [
                    'lab_id' => $this->labId,
                    'code' => $data['code'],
                ],
                [
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'outlet_name' => $data['outlet_name'],
                    'outlet_address' => $data['outlet_address'],
                    'outlet_phone' => null,
                ]
            );
            $this->trackDatabaseOperation('create', $doctor->wasRecentlyCreated);
        }
    }

    public function rules(): array
    {
        return [
            '*.dr_code' => 'nullable|string',
            '*.doctor_name' => 'nullable|string',
        ];
    }
}
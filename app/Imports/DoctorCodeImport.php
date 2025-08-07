<?php

namespace App\Imports;

use App\Models\Doctor;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DoctorCodeImport implements ToArray, WithHeadingRow
{
    public function array(array $array)
    {
        $processedData = [];

        foreach ($array as $row) {
            $processedData[] = [
                'type' => trimOrNull($row['clinicpharmacy']),
                'code' => trimOrNull($row['dr_code']),
                'name' => trimOrNull($row['doctor_name']),
                'outlet_name' => trimOrNull($row['outlet']),
                'outlet_address' => trimOrNull($row['address'])
            ];
        }

        $this->store($processedData);
    }

    public function store(array $processedData)
    {
        $doctors = [];
        foreach ($processedData as $data) {
            $doctor = Doctor::firstOrCreate(
                [
                    'lab_id' => 2,
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

            $doctors[] = $doctor;
        }

        return $doctors;
    }
}

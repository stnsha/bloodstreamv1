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
                'type' => trim($row['clinicpharmacy']),
                'code' => trim($row['dr_code']),
                'name' => trim($row['doctor_name']),
                'outlet_name' => trim($row['outlet']),
                'outlet_address' => trim($row['address'])
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
                    'code' => trim($data['code']),
                ],
                [
                    'name' => trim($data['name']),
                    'type' => trim($data['type']),
                    'outlet_name' => trim($data['outlet_name']),
                    'outlet_address' => trim($data['outlet_address']),
                    'outlet_phone' => null,
                ]
            );

            $doctors[] = $doctor;
        }

        return $doctors;
    }
}

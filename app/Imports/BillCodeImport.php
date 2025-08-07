<?php

namespace App\Imports;

use App\Models\ResultLibrary;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BillCodeImport implements ToArray, WithHeadingRow
{
    public function array(array $array)
    {
        foreach ($array as $row) {
            ResultLibrary::firstOrCreate(
                [
                    'code' => trimOrNull($row['billing_code']),
                ],
                [
                    'type' => 'bill_code',
                    'value' => trimOrNull($row['billing_code']),
                    'description' => 'Innoquest billing code',
                ]
            );
        }
    }
}

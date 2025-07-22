<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithConditionalSheets;

class CodeMappingImport implements WithMultipleSheets
{
    use WithConditionalSheets;

    public function conditionalSheets(): array
    {
        return [
            '2. Profile Code' => new ProfileCodeImport(),
            '3. Doctor Code' => new DoctorCodeImport(),
            '4. Tag On' => new TagOnImport(),
            '5. Reported Test' => new ReportedTestImport(),
            '6. Bill Code' => new BillCodeImport(),
        ];
    }
}

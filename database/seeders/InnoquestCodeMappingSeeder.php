<?php

namespace Database\Seeders;

use App\Http\Controllers\API\ImportController;
use Illuminate\Database\Seeder;

class InnoquestCodeMappingSeeder extends Seeder
{
    public function run(): void
    {
        $importController = new ImportController();
        $importController->innoquestCodeMapping();
    }
}

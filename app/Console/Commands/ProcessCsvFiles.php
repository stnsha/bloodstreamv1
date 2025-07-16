<?php

namespace App\Console\Commands;

use App\Jobs\ImportCsvFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessCsvFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-csv-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process CSV files from SFTP';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $disk = Storage::disk('sftp');

        $is_exist = $disk->exists('/');
        if ($is_exist) {
            $files = $disk->allFiles('/');
            foreach ($files as $file) {
                ImportCsvFile::dispatch($file);
            }
        } else {
            Log::error('Path do not exist.');
        }
    }
}

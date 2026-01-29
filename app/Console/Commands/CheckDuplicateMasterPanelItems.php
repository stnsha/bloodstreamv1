<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDuplicateMasterPanelItems extends Command
{
    protected $signature = 'panel:check-duplicates';

    protected $description = 'Check for duplicate MasterPanelItems';

    public function handle(): int
    {
        $this->info('Checking for duplicate MasterPanelItems...');

        $duplicates = DB::table('master_panel_items')
            ->selectRaw('LOWER(name) as normalized_name')
            ->selectRaw("LOWER(COALESCE(unit, '')) as normalized_unit")
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('GROUP_CONCAT(id ORDER BY id ASC) as ids')
            ->whereNull('deleted_at')
            ->groupByRaw("LOWER(name), LOWER(COALESCE(unit, ''))")
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No duplicate MasterPanelItems found.');
            return self::SUCCESS;
        }

        $this->warn("Found {$duplicates->count()} duplicate groups:");
        $this->newLine();

        foreach ($duplicates as $dup) {
            $this->line("Name: \"{$dup->normalized_name}\"");
            $this->line("Unit: \"" . ($dup->normalized_unit ?: 'NULL') . "\"");
            $this->line("Count: {$dup->count}");
            $this->line("IDs: {$dup->ids}");
            $this->newLine();
        }

        return self::SUCCESS;
    }
}

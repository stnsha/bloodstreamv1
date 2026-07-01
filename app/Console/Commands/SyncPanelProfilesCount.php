<?php

namespace App\Console\Commands;

use App\Models\PanelPanelProfile;
use App\Models\PanelProfilesCount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncPanelProfilesCount extends Command
{
    protected $signature = 'panels:sync-profile-counts
                            {--dry-run : Preview the derived counts without making any changes}';

    protected $description = 'Derive panel_profiles_count.count from panel_panel_profiles (distinct panel_id per panel_profile_id). Existing rows can be manually corrected afterward for profiles whose panel_panel_profiles mapping is incomplete.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        Log::info('SyncPanelProfilesCount started', ['dry_run' => $dryRun]);

        $this->info('Deriving panel profile counts from panel_panel_profiles...');

        $derivedCounts = PanelPanelProfile::select('panel_profile_id', DB::raw('COUNT(DISTINCT panel_id) as derived_count'))
            ->groupBy('panel_profile_id')
            ->get();

        if ($derivedCounts->isEmpty()) {
            $this->info('No panel_panel_profiles rows found — nothing to sync.');
            Log::info('SyncPanelProfilesCount: no rows found');

            return Command::SUCCESS;
        }

        $rows = [];
        $changed = 0;

        foreach ($derivedCounts as $row) {
            $existing = PanelProfilesCount::where('panel_profile_id', $row->panel_profile_id)->first();
            $existingCount = $existing->count ?? null;
            $willChange = $existingCount !== (int) $row->derived_count;

            if ($willChange) {
                $changed++;
            }

            $rows[] = [
                $row->panel_profile_id,
                $existingCount ?? 'N/A',
                $row->derived_count,
                $willChange ? 'UPDATE' : 'UNCHANGED',
            ];
        }

        $this->table(['Panel Profile ID', 'Current Count', 'Derived Count', 'Action'], $rows);
        $this->info("{$changed} row(s) would be created/updated out of {$derivedCounts->count()} profiles checked.");

        if ($dryRun) {
            $this->info('DRY RUN — no changes made.');
            Log::info('SyncPanelProfilesCount: dry run completed', ['changed' => $changed]);

            return Command::SUCCESS;
        }

        foreach ($derivedCounts as $row) {
            PanelProfilesCount::updateOrCreate(
                ['panel_profile_id' => $row->panel_profile_id],
                ['count' => $row->derived_count]
            );
        }

        $this->info("Done. Synced {$derivedCounts->count()} panel profile(s), {$changed} changed.");

        Log::info('SyncPanelProfilesCount completed', [
            'synced' => $derivedCounts->count(),
            'changed' => $changed,
        ]);

        return Command::SUCCESS;
    }
}

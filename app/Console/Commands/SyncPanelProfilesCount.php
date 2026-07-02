<?php

namespace App\Console\Commands;

use App\Models\PanelPanelProfile;
use App\Models\PanelProfile;
use App\Models\PanelProfilesCount;
use App\Models\TestResultItem;
use App\Models\TestResultProfile;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncPanelProfilesCount extends Command
{
    /**
     * lab_id whose profiles are eligible for the test_result_items-based
     * fallback derivation when they have no panel_panel_profiles mapping.
     */
    private const FALLBACK_LAB_ID = 2;

    protected $signature = 'panels:sync-profile-counts
                            {--dry-run : Preview the derived counts without making any changes}
                            {--fallback-cutoff-date=2026-05-31 : For lab_id=2 profiles with no panel_panel_profiles mapping, only consider test_results with collected_date on or before this date}';

    protected $description = 'Derive panel_profiles_count.count from panel_panel_profiles (distinct panel_id per panel_profile_id). For lab_id=2 profiles with no panel_panel_profiles mapping, falls back to counting distinct panels present in the latest qualifying test_result\'s test_result_items. Existing rows can be manually corrected afterward for profiles whose mapping is incomplete.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $cutoffDate = Carbon::parse($this->option('fallback-cutoff-date'))->endOfDay();

        Log::info('SyncPanelProfilesCount started', [
            'dry_run' => $dryRun,
            'fallback_cutoff_date' => $cutoffDate->toDateString(),
        ]);

        $this->info('Deriving panel profile counts from panel_panel_profiles...');

        $derivedCounts = PanelPanelProfile::select('panel_profile_id', DB::raw('COUNT(DISTINCT panel_id) as derived_count'))
            ->groupBy('panel_profile_id')
            ->get();

        $this->info('Deriving fallback counts for lab_id=' . self::FALLBACK_LAB_ID . ' profiles without a panel_panel_profiles mapping...');

        $fallbackRows = $this->deriveFallbackCounts($derivedCounts->pluck('panel_profile_id'), $cutoffDate);

        if ($derivedCounts->isEmpty() && empty($fallbackRows)) {
            $this->info('No panel_panel_profiles rows and no eligible fallback profiles found — nothing to sync.');
            Log::info('SyncPanelProfilesCount: no rows found');

            return Command::SUCCESS;
        }

        $mergedRows = [];

        foreach ($derivedCounts as $row) {
            $mergedRows[] = [
                'panel_profile_id' => $row->panel_profile_id,
                'derived_count' => (int) $row->derived_count,
                'source' => 'panel_panel_profiles',
            ];
        }

        foreach ($fallbackRows as $row) {
            $mergedRows[] = $row;
        }

        $rows = [];
        $changed = 0;

        foreach ($mergedRows as $row) {
            $existing = PanelProfilesCount::where('panel_profile_id', $row['panel_profile_id'])->first();
            $existingCount = $existing->count ?? null;
            $willChange = $existingCount !== $row['derived_count'];

            if ($willChange) {
                $changed++;
            }

            $rows[] = [
                $row['panel_profile_id'],
                $existingCount ?? 'N/A',
                $row['derived_count'],
                $row['source'],
                $willChange ? 'UPDATE' : 'UNCHANGED',
            ];
        }

        $this->table(['Panel Profile ID', 'Current Count', 'Derived Count', 'Source', 'Action'], $rows);
        $this->info("{$changed} row(s) would be created/updated out of " . count($mergedRows) . ' profiles checked.');

        if ($dryRun) {
            $this->info('DRY RUN — no changes made.');
            Log::info('SyncPanelProfilesCount: dry run completed', [
                'changed' => $changed,
                'primary_count' => $derivedCounts->count(),
                'fallback_count' => count($fallbackRows),
            ]);

            return Command::SUCCESS;
        }

        foreach ($mergedRows as $row) {
            PanelProfilesCount::updateOrCreate(
                ['panel_profile_id' => $row['panel_profile_id']],
                ['count' => $row['derived_count']]
            );
        }

        $this->info('Done. Synced ' . count($mergedRows) . " panel profile(s), {$changed} changed.");

        Log::info('SyncPanelProfilesCount completed', [
            'synced' => count($mergedRows),
            'changed' => $changed,
            'primary_count' => $derivedCounts->count(),
            'fallback_count' => count($fallbackRows),
        ]);

        return Command::SUCCESS;
    }

    /**
     * For lab_id=2 profiles with no panel_panel_profiles mapping, derive a count
     * from the distinct panels present in the latest qualifying test_result's
     * test_result_items (collected_date <= cutoff). Profiles with no qualifying
     * test_result are logged and skipped, leaving any existing count untouched.
     *
     * @param  \Illuminate\Support\Collection  $mappedProfileIds
     * @return array<int, array{panel_profile_id: int, derived_count: int, source: string}>
     */
    private function deriveFallbackCounts($mappedProfileIds, Carbon $cutoffDate): array
    {
        $fallbackProfileIds = PanelProfile::where('lab_id', self::FALLBACK_LAB_ID)
            ->whereNotIn('id', $mappedProfileIds)
            ->pluck('id');

        $fallbackRows = [];

        foreach ($fallbackProfileIds as $panelProfileId) {
            $latestTestResultId = TestResultProfile::query()
                ->join('test_results', 'test_results.id', '=', 'test_result_profiles.test_result_id')
                ->where('test_result_profiles.panel_profile_id', $panelProfileId)
                ->where('test_results.collected_date', '<=', $cutoffDate)
                ->whereNull('test_results.deleted_at')
                ->whereNull('test_result_profiles.deleted_at')
                ->orderByDesc('test_results.collected_date')
                ->value('test_result_profiles.test_result_id');

            if (! $latestTestResultId) {
                Log::warning('SyncPanelProfilesCount: no qualifying test_result found for fallback profile, skipping', [
                    'panel_profile_id' => $panelProfileId,
                    'lab_id' => self::FALLBACK_LAB_ID,
                    'fallback_cutoff_date' => $cutoffDate->toDateString(),
                ]);

                continue;
            }

            $derivedCount = TestResultItem::where('test_result_id', $latestTestResultId)
                ->with('panelPanelItem')
                ->get()
                ->pluck('panelPanelItem.panel_id')
                ->filter()
                ->unique()
                ->count();

            $fallbackRows[] = [
                'panel_profile_id' => $panelProfileId,
                'derived_count' => $derivedCount,
                'source' => 'fallback (lab_id=' . self::FALLBACK_LAB_ID . ", test_result_id={$latestTestResultId})",
            ];
        }

        return $fallbackRows;
    }
}

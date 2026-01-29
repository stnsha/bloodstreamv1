<?php

namespace App\Console\Commands;

use App\Models\MasterPanelItem;
use App\Models\PanelItem;
use App\Models\PanelMergeLog;
use App\Services\PanelMergeLogService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixMismatchedPanelItemReferences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'panel:fix-mismatched-references
        {--dry-run : Preview changes without executing}
        {--fix : Actually fix the mismatched references}
        {--detailed : Show detailed progress}
        {--log-id= : PanelMergeLog ID for tracking changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and fix PanelItems where unit does not match their MasterPanelItem unit';

    /**
     * Statistics tracking.
     *
     * @var array<string, int>
     */
    protected array $stats = [
        'mismatched_found' => 0,
        'fixable' => 0,
        'fixed' => 0,
        'no_match_found' => 0,
        'multiple_matches' => 0,
    ];

    /**
     * Whether we're in dry-run mode.
     */
    protected bool $dryRun = false;

    /**
     * Whether to actually fix.
     */
    protected bool $shouldFix = false;

    /**
     * Whether detailed output is enabled.
     */
    protected bool $detailedOutput = false;

    /**
     * Log service for tracking changes.
     */
    protected PanelMergeLogService $logService;

    /**
     * Execute the console command.
     */
    public function handle(PanelMergeLogService $logService): int
    {
        $this->logService = $logService;
        $this->dryRun = $this->option('dry-run');
        $this->shouldFix = $this->option('fix');
        $this->detailedOutput = $this->option('detailed');

        // Set up log service if log-id provided
        $logId = $this->option('log-id');
        if ($logId) {
            $log = PanelMergeLog::find($logId);
            if ($log) {
                $this->logService->setCurrentLog($log)->setDryRun($this->dryRun);
            }
        }

        if (!$this->dryRun && !$this->shouldFix) {
            $this->info('Running in report-only mode. Use --dry-run to preview fixes, or --fix to apply them.');
        }

        $mode = $this->shouldFix ? '[EXECUTE]' : ($this->dryRun ? '[DRY-RUN]' : '[REPORT]');
        $this->info("{$mode} Finding mismatched PanelItem references...\n");

        Log::info('FixMismatchedPanelItemReferences: Starting', [
            'dry_run' => $this->dryRun,
            'fix' => $this->shouldFix,
        ]);

        try {
            $mismatches = $this->findMismatchedReferences();

            if ($mismatches->isEmpty()) {
                $this->info('No mismatched references found.');
                return self::SUCCESS;
            }

            $this->info("Found {$mismatches->count()} mismatched PanelItem references:\n");

            // Group by MasterPanelItem for better display
            $grouped = $mismatches->groupBy('master_panel_item_id');

            foreach ($grouped as $masterPanelItemId => $items) {
                $masterItem = MasterPanelItem::find($masterPanelItemId);
                $masterName = $masterItem ? $masterItem->name : 'DELETED';
                $masterUnit = $masterItem ? ($masterItem->unit ?? 'NULL') : 'N/A';

                $this->warn("MasterPanelItem #{$masterPanelItemId}: \"{$masterName}\" (unit: {$masterUnit})");

                foreach ($items as $item) {
                    $this->stats['mismatched_found']++;
                    $panelItemUnit = $item->panel_item_unit ?? 'NULL';

                    $this->line("  - PanelItem #{$item->panel_item_id} (lab {$item->lab_id}): \"{$item->panel_item_name}\" (unit: {$panelItemUnit})");

                    // Try to find a better matching MasterPanelItem
                    $betterMatch = $this->findBetterMatch($item);

                    if ($betterMatch === null) {
                        $this->line("      [NO MATCH] No MasterPanelItem found with matching unit");
                        $this->stats['no_match_found']++;
                    } elseif ($betterMatch === false) {
                        $this->line("      [MULTIPLE] Multiple potential matches found - manual review needed");
                        $this->stats['multiple_matches']++;
                    } else {
                        $this->info("      [FIXABLE] Should point to MasterPanelItem #{$betterMatch->id}: \"{$betterMatch->name}\" (unit: " . ($betterMatch->unit ?? 'NULL') . ")");
                        $this->stats['fixable']++;

                        if ($this->shouldFix || $this->dryRun) {
                            $this->fixReference($item->panel_item_id, $betterMatch->id);
                        }
                    }
                }
                $this->line('');
            }

            $this->displaySummary();

            Log::info('FixMismatchedPanelItemReferences: Completed', $this->stats);

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error("Command failed: {$e->getMessage()}");
            Log::error('FixMismatchedPanelItemReferences: Command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Find PanelItems where unit doesn't match MasterPanelItem unit.
     */
    protected function findMismatchedReferences()
    {
        return DB::table('panel_items as pi')
            ->join('master_panel_items as mpi', 'pi.master_panel_item_id', '=', 'mpi.id')
            ->whereNull('pi.deleted_at')
            ->whereNull('mpi.deleted_at')
            ->whereRaw("LOWER(COALESCE(pi.unit, '')) != LOWER(COALESCE(mpi.unit, ''))")
            ->select([
                'pi.id as panel_item_id',
                'pi.lab_id',
                'pi.name as panel_item_name',
                'pi.unit as panel_item_unit',
                'pi.master_panel_item_id',
                'mpi.name as master_panel_item_name',
                'mpi.unit as master_panel_item_unit',
            ])
            ->orderBy('mpi.id')
            ->orderBy('pi.id')
            ->get();
    }

    /**
     * Find a better matching MasterPanelItem for the given PanelItem.
     *
     * @return MasterPanelItem|null|false null = no match, false = multiple matches
     */
    protected function findBetterMatch(object $panelItem)
    {
        $panelItemUnit = $panelItem->panel_item_unit ?? '';

        // Find MasterPanelItems with matching unit (case-insensitive)
        // Also try to match by similar name
        $candidates = MasterPanelItem::whereNull('deleted_at')
            ->whereRaw("LOWER(COALESCE(unit, '')) = LOWER(?)", [$panelItemUnit])
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        // Try to find exact name match first
        $panelItemName = strtolower($panelItem->panel_item_name ?? '');
        $exactMatch = $candidates->first(function ($candidate) use ($panelItemName) {
            return strtolower($candidate->name) === $panelItemName;
        });

        if ($exactMatch) {
            return $exactMatch;
        }

        // Try to find partial name match (name starts with or ends with, not just contains)
        // This prevents "Platelet" from matching "AST to Platelet Ratio Index"
        $partialMatches = $candidates->filter(function ($candidate) use ($panelItemName) {
            $candidateName = strtolower($candidate->name);
            // Check if one name starts with or ends with the other
            // Or if they share a significant common prefix/suffix
            return str_starts_with($candidateName, $panelItemName)
                || str_starts_with($panelItemName, $candidateName)
                || str_ends_with($candidateName, $panelItemName)
                || str_ends_with($panelItemName, $candidateName);
        });

        if ($partialMatches->count() === 1) {
            return $partialMatches->first();
        }

        if ($partialMatches->count() > 1) {
            // Multiple partial matches - show them for context
            if ($this->detailedOutput) {
                $this->line("      Candidates:");
                foreach ($partialMatches as $match) {
                    $this->line("        - #{$match->id}: \"{$match->name}\" (unit: " . ($match->unit ?? 'NULL') . ")");
                }
            }
            return false;
        }

        // No name match at all, but we have candidates with matching unit
        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        // Multiple candidates, none with matching name
        if ($this->detailedOutput) {
            $this->line("      Candidates with matching unit but no name match:");
            foreach ($candidates->take(5) as $match) {
                $this->line("        - #{$match->id}: \"{$match->name}\" (unit: " . ($match->unit ?? 'NULL') . ")");
            }
            if ($candidates->count() > 5) {
                $this->line("        ... and " . ($candidates->count() - 5) . " more");
            }
        }
        return false;
    }

    /**
     * Fix a PanelItem reference to point to the correct MasterPanelItem.
     */
    protected function fixReference(int $panelItemId, int $newMasterPanelItemId): void
    {
        if ($this->dryRun) {
            $this->line("      [DRY-RUN] Would update PanelItem #{$panelItemId} -> MasterPanelItem #{$newMasterPanelItemId}");
            return;
        }

        try {
            DB::beginTransaction();

            $panelItem = PanelItem::find($panelItemId);
            $oldMasterPanelItemId = $panelItem->master_panel_item_id;
            $newMasterPanelItem = MasterPanelItem::find($newMasterPanelItemId);

            PanelItem::where('id', $panelItemId)
                ->update(['master_panel_item_id' => $newMasterPanelItemId]);

            DB::commit();

            $this->stats['fixed']++;
            $this->line("      [FIXED] Updated PanelItem #{$panelItemId} -> MasterPanelItem #{$newMasterPanelItemId}");

            // Log the change
            $this->logService->logRepointed(
                'PanelItem',
                $panelItemId,
                $panelItem->name,
                $oldMasterPanelItemId,
                $newMasterPanelItemId,
                "Fixed unit mismatch: now points to MasterPanelItem #{$newMasterPanelItemId}: \"{$newMasterPanelItem->name}\" (unit: " . ($newMasterPanelItem->unit ?? 'NULL') . ")"
            );

            Log::info('FixMismatchedPanelItemReferences: Fixed reference', [
                'panel_item_id' => $panelItemId,
                'new_master_panel_item_id' => $newMasterPanelItemId,
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            $this->error("      [ERROR] Failed to fix PanelItem #{$panelItemId}: {$e->getMessage()}");
            Log::error('FixMismatchedPanelItemReferences: Fix failed', [
                'panel_item_id' => $panelItemId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Display summary statistics.
     */
    protected function displaySummary(): void
    {
        $this->info("=== Summary ===");
        $this->info("Mismatched references found: {$this->stats['mismatched_found']}");
        $this->info("  - Fixable (single match): {$this->stats['fixable']}");
        $this->info("  - No matching MasterPanelItem: {$this->stats['no_match_found']}");
        $this->info("  - Multiple matches (manual review): {$this->stats['multiple_matches']}");

        if ($this->shouldFix) {
            $this->info("  - Actually fixed: {$this->stats['fixed']}");
        }
    }
}

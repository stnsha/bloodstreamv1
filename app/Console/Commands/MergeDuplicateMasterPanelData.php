<?php

namespace App\Console\Commands;

use App\Models\MasterPanel;
use App\Models\MasterPanelItem;
use App\Models\Panel;
use App\Models\PanelInterpretation;
use App\Models\PanelItem;
use App\Models\PanelMergeLog;
use App\Models\PanelPanelItem;
use App\Models\ReferenceRange;
use App\Models\TestResultItem;
use App\Models\TestResultSpecialTest;
use App\Services\PanelMergeLogService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MergeDuplicateMasterPanelData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'panel:merge-duplicates
        {--dry-run : Preview changes without executing}
        {--items-only : Only merge MasterPanelItems, skip MasterPanels}
        {--panels-only : Only merge MasterPanels, skip MasterPanelItems}
        {--limit=0 : Limit number of duplicate groups to process (0 = unlimited)}
        {--detailed : Show detailed progress}
        {--log-id= : PanelMergeLog ID for tracking changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge duplicate MasterPanel and MasterPanelItem records with full cascade';

    /**
     * Statistics tracking.
     *
     * @var array<string, int>
     */
    protected array $stats = [
        // MasterPanelItem stats
        'master_panel_item_groups' => 0,
        'master_panel_items_deleted' => 0,
        'panel_items_merged' => 0,
        'panel_items_repointed' => 0,

        // MasterPanel stats
        'master_panel_groups' => 0,
        'master_panels_deleted' => 0,
        'panels_merged' => 0,
        'panels_repointed' => 0,

        // Shared downstream stats
        'panel_panel_items_merged' => 0,
        'panel_panel_items_repointed' => 0,
        'test_result_items_updated' => 0,
        'reference_ranges_merged' => 0,
        'panel_interpretations_merged' => 0,
        'test_result_special_tests_updated' => 0,
    ];

    /**
     * Whether we're in dry-run mode.
     */
    protected bool $dryRun = false;

    /**
     * Whether verbose output is enabled.
     */
    protected bool $verboseOutput = false;

    /**
     * Log service for tracking changes.
     */
    protected PanelMergeLogService $logService;

    /**
     * The current log entry.
     */
    protected ?PanelMergeLog $log = null;

    /**
     * Execute the console command.
     */
    public function handle(PanelMergeLogService $logService): int
    {
        $this->logService = $logService;
        $this->dryRun = $this->option('dry-run');
        $this->verboseOutput = $this->option('detailed');
        $itemsOnly = $this->option('items-only');
        $panelsOnly = $this->option('panels-only');
        $limit = (int) $this->option('limit');

        // Set up log - use provided log-id or create new one
        $logId = $this->option('log-id');
        if ($logId) {
            $this->log = PanelMergeLog::find($logId);
        } else {
            // Create log entry for CLI execution
            $this->log = PanelMergeLog::create([
                'command' => 'panel:merge-duplicates',
                'status' => 'running',
                'is_dry_run' => $this->dryRun,
                'options' => array_filter([
                    'items-only' => $itemsOnly,
                    'panels-only' => $panelsOnly,
                    'limit' => $limit,
                    'detailed' => $this->verboseOutput,
                ]),
                'started_at' => now(),
            ]);
        }

        if ($this->log) {
            $this->logService->setCurrentLog($this->log)->setDryRun($this->dryRun);
        }

        $mode = $this->dryRun ? '[DRY-RUN]' : '[EXECUTE]';
        $this->info("{$mode} Starting duplicate merge process...");

        Log::info('MergeDuplicateMasterPanelData: Starting', [
            'dry_run' => $this->dryRun,
            'items_only' => $itemsOnly,
            'panels_only' => $panelsOnly,
            'limit' => $limit,
        ]);

        try {
            // Phase A: Merge MasterPanelItems first (unless panels-only)
            if (!$panelsOnly) {
                $this->info("\n=== Phase A: Merging MasterPanelItems ===");
                $this->processMasterPanelItems($limit);
            }

            // Phase B: Merge MasterPanels (unless items-only)
            if (!$itemsOnly) {
                $this->info("\n=== Phase B: Merging MasterPanels ===");
                $this->processMasterPanels($limit);
            }

            // Display summary
            $this->displaySummary();

            // Run verification if not dry-run
            if (!$this->dryRun) {
                $this->info("\n=== Verification ===");
                $this->runVerification();
            }

            // Update log with success
            if ($this->log) {
                $this->log->update([
                    'status' => 'completed',
                    'stats' => $this->stats,
                    'completed_at' => now(),
                ]);
            }

            Log::info('MergeDuplicateMasterPanelData: Completed successfully', $this->stats);

            return self::SUCCESS;

        } catch (Exception $e) {
            // Update log with failure
            if ($this->log) {
                $this->log->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'stats' => $this->stats,
                    'completed_at' => now(),
                ]);
            }

            $this->error("Command failed: {$e->getMessage()}");
            Log::error('MergeDuplicateMasterPanelData: Command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Process and merge duplicate MasterPanelItems.
     */
    protected function processMasterPanelItems(int $limit): void
    {
        $duplicateGroups = $this->findDuplicateMasterPanelItems();

        if ($duplicateGroups->isEmpty()) {
            $this->info('No duplicate MasterPanelItems found.');
            return;
        }

        $this->info("Found {$duplicateGroups->count()} duplicate MasterPanelItem groups.");

        $processed = 0;
        foreach ($duplicateGroups as $group) {
            if ($limit > 0 && $processed >= $limit) {
                $this->warn("Limit of {$limit} groups reached. Stopping.");
                break;
            }

            $this->processMasterPanelItemGroup($group);
            $processed++;
            $this->stats['master_panel_item_groups']++;
        }
    }

    /**
     * Find duplicate MasterPanelItems based on name + unit (case-insensitive).
     */
    protected function findDuplicateMasterPanelItems(): Collection
    {
        $duplicates = DB::table('master_panel_items')
            ->selectRaw('LOWER(name) as normalized_name')
            ->selectRaw("LOWER(COALESCE(unit, '')) as normalized_unit")
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('GROUP_CONCAT(id ORDER BY id ASC) as ids')
            ->whereNull('deleted_at')
            ->groupByRaw("LOWER(name), LOWER(COALESCE(unit, ''))")
            ->havingRaw('COUNT(*) > 1')
            ->get();

        return $duplicates->map(function ($row) {
            $ids = array_map('intval', explode(',', $row->ids));
            return (object) [
                'normalized_name' => $row->normalized_name,
                'normalized_unit' => $row->normalized_unit,
                'canonical_id' => $ids[0],
                'duplicate_ids' => array_slice($ids, 1),
                'all_ids' => $ids,
            ];
        });
    }

    /**
     * Process a single MasterPanelItem duplicate group.
     */
    protected function processMasterPanelItemGroup(object $group): void
    {
        $canonicalId = $group->canonical_id;
        $duplicateIds = $group->duplicate_ids;

        $this->verboseLine("Processing MasterPanelItem group: canonical={$canonicalId}, duplicates=" . implode(',', $duplicateIds));

        try {
            DB::beginTransaction();

            foreach ($duplicateIds as $duplicateId) {
                $this->mergeMasterPanelItem($canonicalId, $duplicateId);
            }

            if (!$this->dryRun) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            Log::info('MergeDuplicateMasterPanelData: MasterPanelItem group merged', [
                'canonical_id' => $canonicalId,
                'duplicate_ids' => $duplicateIds,
                'dry_run' => $this->dryRun,
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            $this->error("Failed to merge MasterPanelItem group (canonical={$canonicalId}): {$e->getMessage()}");
            Log::error('MergeDuplicateMasterPanelData: MasterPanelItem group merge failed', [
                'canonical_id' => $canonicalId,
                'duplicate_ids' => $duplicateIds,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Merge a duplicate MasterPanelItem into the canonical one.
     */
    protected function mergeMasterPanelItem(int $canonicalId, int $duplicateId): void
    {
        $duplicate = MasterPanelItem::find($duplicateId);
        $canonical = MasterPanelItem::find($canonicalId);

        $this->verboseLine("  Merging MasterPanelItem {$duplicateId} -> {$canonicalId}");

        // Find PanelItems pointing to the duplicate
        $duplicatePanelItems = PanelItem::where('master_panel_item_id', $duplicateId)->get();

        foreach ($duplicatePanelItems as $duplicatePanelItem) {
            // Check if canonical MasterPanelItem already has a PanelItem for this lab
            $canonicalPanelItem = PanelItem::where('master_panel_item_id', $canonicalId)
                ->where('lab_id', $duplicatePanelItem->lab_id)
                ->first();

            if ($canonicalPanelItem) {
                // Merge PanelItems: move PanelPanelItems from duplicate to canonical
                $this->mergePanelItems($canonicalPanelItem->id, $duplicatePanelItem->id);
                $this->stats['panel_items_merged']++;
            } else {
                // Repoint PanelItem to canonical MasterPanelItem
                $this->verboseLine("    Repointing PanelItem {$duplicatePanelItem->id} to MasterPanelItem {$canonicalId}");
                if (!$this->dryRun) {
                    $duplicatePanelItem->update(['master_panel_item_id' => $canonicalId]);
                }
                $this->logService->logRepointed(
                    'PanelItem',
                    $duplicatePanelItem->id,
                    $duplicatePanelItem->name,
                    $duplicateId,
                    $canonicalId,
                    "Repointed to MasterPanelItem #{$canonicalId}: {$canonical->name}"
                );
                $this->stats['panel_items_repointed']++;
            }
        }

        // Soft delete the duplicate MasterPanelItem
        $this->verboseLine("    Soft deleting MasterPanelItem {$duplicateId}");
        if (!$this->dryRun) {
            MasterPanelItem::where('id', $duplicateId)->delete();
        }
        $this->logService->logMerged(
            'MasterPanelItem',
            $duplicateId,
            $duplicate->name ?? null,
            $canonicalId,
            $canonical->name ?? null,
            "Merged into #{$canonicalId} (unit: " . ($canonical->unit ?? 'NULL') . ")"
        );
        $this->stats['master_panel_items_deleted']++;
    }

    /**
     * Merge PanelItems by moving PanelPanelItems from duplicate to canonical.
     */
    protected function mergePanelItems(int $canonicalPanelItemId, int $duplicatePanelItemId): void
    {
        $duplicate = PanelItem::find($duplicatePanelItemId);
        $canonical = PanelItem::find($canonicalPanelItemId);

        $this->verboseLine("    Merging PanelItem {$duplicatePanelItemId} -> {$canonicalPanelItemId}");

        // Find PanelPanelItems for the duplicate PanelItem
        $duplicatePPIs = PanelPanelItem::where('panel_item_id', $duplicatePanelItemId)->get();

        foreach ($duplicatePPIs as $duplicatePPI) {
            // Check if canonical PanelItem already has a PanelPanelItem for this Panel
            $canonicalPPI = PanelPanelItem::where('panel_item_id', $canonicalPanelItemId)
                ->where('panel_id', $duplicatePPI->panel_id)
                ->first();

            if ($canonicalPPI) {
                // Merge PanelPanelItems: move downstream records
                $this->mergePanelPanelItems($canonicalPPI->id, $duplicatePPI->id);
                $this->stats['panel_panel_items_merged']++;
            } else {
                // Repoint PanelPanelItem to canonical PanelItem
                $this->verboseLine("      Repointing PanelPanelItem {$duplicatePPI->id} to PanelItem {$canonicalPanelItemId}");
                if (!$this->dryRun) {
                    $duplicatePPI->update(['panel_item_id' => $canonicalPanelItemId]);
                }
                $this->stats['panel_panel_items_repointed']++;
            }
        }

        // Soft delete the duplicate PanelItem
        $this->verboseLine("    Soft deleting PanelItem {$duplicatePanelItemId}");
        if (!$this->dryRun) {
            PanelItem::where('id', $duplicatePanelItemId)->delete();
        }
        $this->logService->logMerged(
            'PanelItem',
            $duplicatePanelItemId,
            $duplicate->name ?? null,
            $canonicalPanelItemId,
            $canonical->name ?? null,
            "Merged into PanelItem #{$canonicalPanelItemId}"
        );
    }

    /**
     * Merge PanelPanelItems by moving downstream records from duplicate to canonical.
     */
    protected function mergePanelPanelItems(int $canonicalPPIId, int $duplicatePPIId): void
    {
        $this->verboseLine("      Merging PanelPanelItem {$duplicatePPIId} -> {$canonicalPPIId}");

        // Update TestResultItems
        $testResultItemCount = TestResultItem::where('panel_panel_item_id', $duplicatePPIId)->count();
        if ($testResultItemCount > 0) {
            $this->verboseLine("        Updating {$testResultItemCount} TestResultItems");
            if (!$this->dryRun) {
                TestResultItem::where('panel_panel_item_id', $duplicatePPIId)
                    ->update(['panel_panel_item_id' => $canonicalPPIId]);
            }
            $this->stats['test_result_items_updated'] += $testResultItemCount;
        }

        // Merge ReferenceRanges (dedupe by value)
        $this->mergeReferenceRanges($canonicalPPIId, $duplicatePPIId);

        // Merge PanelInterpretations (dedupe by range + interpretation)
        $this->mergePanelInterpretations($canonicalPPIId, $duplicatePPIId);

        // Update TestResultSpecialTests
        $specialTestCount = TestResultSpecialTest::where('panel_panel_item_id', $duplicatePPIId)->count();
        if ($specialTestCount > 0) {
            $this->verboseLine("        Updating {$specialTestCount} TestResultSpecialTests");
            if (!$this->dryRun) {
                TestResultSpecialTest::where('panel_panel_item_id', $duplicatePPIId)
                    ->update(['panel_panel_item_id' => $canonicalPPIId]);
            }
            $this->stats['test_result_special_tests_updated'] += $specialTestCount;
        }

        // Delete the duplicate PanelPanelItem
        $this->verboseLine("      Deleting PanelPanelItem {$duplicatePPIId}");
        if (!$this->dryRun) {
            PanelPanelItem::where('id', $duplicatePPIId)->delete();
        }
    }

    /**
     * Merge ReferenceRanges, deduplicating by value.
     */
    protected function mergeReferenceRanges(int $canonicalPPIId, int $duplicatePPIId): void
    {
        $duplicateRanges = ReferenceRange::where('panel_panel_item_id', $duplicatePPIId)->get();

        foreach ($duplicateRanges as $duplicateRange) {
            // Check if canonical already has this value
            $existingRange = ReferenceRange::where('panel_panel_item_id', $canonicalPPIId)
                ->where('value', $duplicateRange->value)
                ->first();

            if ($existingRange) {
                // Update any TestResultItems pointing to this ReferenceRange
                $affectedItems = TestResultItem::where('reference_range_id', $duplicateRange->id)->count();
                if ($affectedItems > 0) {
                    $this->verboseLine("        Repointing {$affectedItems} TestResultItems from ReferenceRange {$duplicateRange->id} to {$existingRange->id}");
                    if (!$this->dryRun) {
                        TestResultItem::where('reference_range_id', $duplicateRange->id)
                            ->update(['reference_range_id' => $existingRange->id]);
                    }
                }

                // Soft delete the duplicate ReferenceRange
                $this->verboseLine("        Soft deleting duplicate ReferenceRange {$duplicateRange->id}");
                if (!$this->dryRun) {
                    $duplicateRange->delete();
                }
                $this->stats['reference_ranges_merged']++;
            } else {
                // Repoint to canonical PPI
                $this->verboseLine("        Repointing ReferenceRange {$duplicateRange->id} to PanelPanelItem {$canonicalPPIId}");
                if (!$this->dryRun) {
                    $duplicateRange->update(['panel_panel_item_id' => $canonicalPPIId]);
                }
            }
        }
    }

    /**
     * Merge PanelInterpretations, deduplicating by range + interpretation.
     */
    protected function mergePanelInterpretations(int $canonicalPPIId, int $duplicatePPIId): void
    {
        $duplicateInterpretations = PanelInterpretation::where('panel_panel_item_id', $duplicatePPIId)->get();

        foreach ($duplicateInterpretations as $duplicateInterp) {
            // Check if canonical already has this range + interpretation combo
            $existingInterp = PanelInterpretation::where('panel_panel_item_id', $canonicalPPIId)
                ->where('range', $duplicateInterp->range)
                ->where('interpretation', $duplicateInterp->interpretation)
                ->first();

            if ($existingInterp) {
                // Update any TestResultSpecialTests pointing to this interpretation
                $affectedTests = TestResultSpecialTest::where('panel_interpretation_id', $duplicateInterp->id)->count();
                if ($affectedTests > 0) {
                    $this->verboseLine("        Repointing {$affectedTests} TestResultSpecialTests from PanelInterpretation {$duplicateInterp->id} to {$existingInterp->id}");
                    if (!$this->dryRun) {
                        TestResultSpecialTest::where('panel_interpretation_id', $duplicateInterp->id)
                            ->update(['panel_interpretation_id' => $existingInterp->id]);
                    }
                }

                // Delete the duplicate PanelInterpretation
                $this->verboseLine("        Deleting duplicate PanelInterpretation {$duplicateInterp->id}");
                if (!$this->dryRun) {
                    $duplicateInterp->delete();
                }
                $this->stats['panel_interpretations_merged']++;
            } else {
                // Repoint to canonical PPI
                $this->verboseLine("        Repointing PanelInterpretation {$duplicateInterp->id} to PanelPanelItem {$canonicalPPIId}");
                if (!$this->dryRun) {
                    $duplicateInterp->update(['panel_panel_item_id' => $canonicalPPIId]);
                }
            }
        }
    }

    /**
     * Process and merge duplicate MasterPanels.
     */
    protected function processMasterPanels(int $limit): void
    {
        $duplicateGroups = $this->findDuplicateMasterPanels();

        if ($duplicateGroups->isEmpty()) {
            $this->info('No duplicate MasterPanels found.');
            return;
        }

        $this->info("Found {$duplicateGroups->count()} duplicate MasterPanel groups.");

        $processed = 0;
        foreach ($duplicateGroups as $group) {
            if ($limit > 0 && $processed >= $limit) {
                $this->warn("Limit of {$limit} groups reached. Stopping.");
                break;
            }

            $this->processMasterPanelGroup($group);
            $processed++;
            $this->stats['master_panel_groups']++;
        }
    }

    /**
     * Find duplicate MasterPanels based on name (case-insensitive) AND same MasterPanelItem set.
     */
    protected function findDuplicateMasterPanels(): Collection
    {
        // First, find MasterPanels with the same name
        $nameGroups = DB::table('master_panels')
            ->selectRaw('LOWER(name) as normalized_name')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('GROUP_CONCAT(id ORDER BY id ASC) as ids')
            ->whereNull('deleted_at')
            ->groupByRaw('LOWER(name)')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $duplicateGroups = collect();

        foreach ($nameGroups as $nameGroup) {
            $masterPanelIds = array_map('intval', explode(',', $nameGroup->ids));

            // Group by MasterPanelItem set hash
            $hashGroups = [];
            foreach ($masterPanelIds as $masterPanelId) {
                $itemsHash = $this->getMasterPanelItemsHash($masterPanelId);
                if (!isset($hashGroups[$itemsHash])) {
                    $hashGroups[$itemsHash] = [];
                }
                $hashGroups[$itemsHash][] = $masterPanelId;
            }

            // Only keep groups with more than one MasterPanel (actual duplicates)
            foreach ($hashGroups as $hash => $ids) {
                if (count($ids) > 1) {
                    sort($ids);
                    $duplicateGroups->push((object) [
                        'normalized_name' => $nameGroup->normalized_name,
                        'items_hash' => $hash,
                        'canonical_id' => $ids[0],
                        'duplicate_ids' => array_slice($ids, 1),
                        'all_ids' => $ids,
                    ]);
                }
            }
        }

        return $duplicateGroups;
    }

    /**
     * Get a hash representing the MasterPanelItems associated with a MasterPanel.
     */
    protected function getMasterPanelItemsHash(int $masterPanelId): string
    {
        // Get MasterPanelItem IDs via: MasterPanel -> Panel -> PanelPanelItem -> PanelItem -> MasterPanelItem
        $masterPanelItemIds = MasterPanelItem::query()
            ->whereHas('panelItems.panels', function ($query) use ($masterPanelId) {
                $query->where('master_panel_id', $masterPanelId);
            })
            ->pluck('id')
            ->sort()
            ->values()
            ->toArray();

        return md5(implode(',', $masterPanelItemIds));
    }

    /**
     * Process a single MasterPanel duplicate group.
     */
    protected function processMasterPanelGroup(object $group): void
    {
        $canonicalId = $group->canonical_id;
        $duplicateIds = $group->duplicate_ids;

        $this->verboseLine("Processing MasterPanel group: canonical={$canonicalId}, duplicates=" . implode(',', $duplicateIds));

        try {
            DB::beginTransaction();

            foreach ($duplicateIds as $duplicateId) {
                $this->mergeMasterPanel($canonicalId, $duplicateId);
            }

            if (!$this->dryRun) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            Log::info('MergeDuplicateMasterPanelData: MasterPanel group merged', [
                'canonical_id' => $canonicalId,
                'duplicate_ids' => $duplicateIds,
                'dry_run' => $this->dryRun,
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            $this->error("Failed to merge MasterPanel group (canonical={$canonicalId}): {$e->getMessage()}");
            Log::error('MergeDuplicateMasterPanelData: MasterPanel group merge failed', [
                'canonical_id' => $canonicalId,
                'duplicate_ids' => $duplicateIds,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Merge a duplicate MasterPanel into the canonical one.
     */
    protected function mergeMasterPanel(int $canonicalId, int $duplicateId): void
    {
        $duplicate = MasterPanel::find($duplicateId);
        $canonical = MasterPanel::find($canonicalId);

        $this->verboseLine("  Merging MasterPanel {$duplicateId} -> {$canonicalId}");

        // Find Panels pointing to the duplicate MasterPanel
        $duplicatePanels = Panel::where('master_panel_id', $duplicateId)->get();

        foreach ($duplicatePanels as $duplicatePanel) {
            // Check if canonical MasterPanel already has a Panel for this lab + category
            $canonicalPanel = Panel::where('master_panel_id', $canonicalId)
                ->where('lab_id', $duplicatePanel->lab_id)
                ->where('panel_category_id', $duplicatePanel->panel_category_id)
                ->first();

            if ($canonicalPanel) {
                // Merge Panels: move PanelPanelItems from duplicate to canonical
                $this->mergePanels($canonicalPanel->id, $duplicatePanel->id);
                $this->stats['panels_merged']++;
            } else {
                // Repoint Panel to canonical MasterPanel
                $this->verboseLine("    Repointing Panel {$duplicatePanel->id} to MasterPanel {$canonicalId}");
                if (!$this->dryRun) {
                    $duplicatePanel->update(['master_panel_id' => $canonicalId]);
                }
                $this->logService->logRepointed(
                    'Panel',
                    $duplicatePanel->id,
                    $duplicatePanel->name,
                    $duplicateId,
                    $canonicalId,
                    "Repointed to MasterPanel #{$canonicalId}: {$canonical->name}"
                );
                $this->stats['panels_repointed']++;
            }
        }

        // Soft delete the duplicate MasterPanel
        $this->verboseLine("    Soft deleting MasterPanel {$duplicateId}");
        if (!$this->dryRun) {
            MasterPanel::where('id', $duplicateId)->delete();
        }
        $this->logService->logMerged(
            'MasterPanel',
            $duplicateId,
            $duplicate->name ?? null,
            $canonicalId,
            $canonical->name ?? null,
            "Merged into MasterPanel #{$canonicalId}"
        );
        $this->stats['master_panels_deleted']++;
    }

    /**
     * Merge Panels by moving PanelPanelItems from duplicate to canonical.
     */
    protected function mergePanels(int $canonicalPanelId, int $duplicatePanelId): void
    {
        $duplicate = Panel::find($duplicatePanelId);
        $canonical = Panel::find($canonicalPanelId);

        $this->verboseLine("    Merging Panel {$duplicatePanelId} -> {$canonicalPanelId}");

        // Find PanelPanelItems for the duplicate Panel
        $duplicatePPIs = PanelPanelItem::where('panel_id', $duplicatePanelId)->get();

        foreach ($duplicatePPIs as $duplicatePPI) {
            // Check if canonical Panel already has a PanelPanelItem for this PanelItem
            $canonicalPPI = PanelPanelItem::where('panel_id', $canonicalPanelId)
                ->where('panel_item_id', $duplicatePPI->panel_item_id)
                ->first();

            if ($canonicalPPI) {
                // Merge PanelPanelItems: move downstream records
                $this->mergePanelPanelItems($canonicalPPI->id, $duplicatePPI->id);
                $this->stats['panel_panel_items_merged']++;
            } else {
                // Repoint PanelPanelItem to canonical Panel
                $this->verboseLine("      Repointing PanelPanelItem {$duplicatePPI->id} to Panel {$canonicalPanelId}");
                if (!$this->dryRun) {
                    $duplicatePPI->update(['panel_id' => $canonicalPanelId]);
                }
                $this->stats['panel_panel_items_repointed']++;
            }
        }

        // Soft delete the duplicate Panel
        $this->verboseLine("    Soft deleting Panel {$duplicatePanelId}");
        if (!$this->dryRun) {
            Panel::where('id', $duplicatePanelId)->delete();
        }
        $this->logService->logMerged(
            'Panel',
            $duplicatePanelId,
            $duplicate->name ?? null,
            $canonicalPanelId,
            $canonical->name ?? null,
            "Merged into Panel #{$canonicalPanelId}"
        );
    }

    /**
     * Display summary statistics.
     */
    protected function displaySummary(): void
    {
        $this->info("\n=== Summary ===");
        $mode = $this->dryRun ? '(DRY-RUN - no changes made)' : '(EXECUTED)';
        $this->info($mode);

        $this->info("\nMasterPanelItem Statistics:");
        $this->info("  Groups processed: {$this->stats['master_panel_item_groups']}");
        $this->info("  MasterPanelItems deleted: {$this->stats['master_panel_items_deleted']}");
        $this->info("  PanelItems merged: {$this->stats['panel_items_merged']}");
        $this->info("  PanelItems repointed: {$this->stats['panel_items_repointed']}");

        $this->info("\nMasterPanel Statistics:");
        $this->info("  Groups processed: {$this->stats['master_panel_groups']}");
        $this->info("  MasterPanels deleted: {$this->stats['master_panels_deleted']}");
        $this->info("  Panels merged: {$this->stats['panels_merged']}");
        $this->info("  Panels repointed: {$this->stats['panels_repointed']}");

        $this->info("\nDownstream Statistics:");
        $this->info("  PanelPanelItems merged: {$this->stats['panel_panel_items_merged']}");
        $this->info("  PanelPanelItems repointed: {$this->stats['panel_panel_items_repointed']}");
        $this->info("  TestResultItems updated: {$this->stats['test_result_items_updated']}");
        $this->info("  ReferenceRanges merged: {$this->stats['reference_ranges_merged']}");
        $this->info("  PanelInterpretations merged: {$this->stats['panel_interpretations_merged']}");
        $this->info("  TestResultSpecialTests updated: {$this->stats['test_result_special_tests_updated']}");
    }

    /**
     * Run verification checks after merge.
     */
    protected function runVerification(): void
    {
        $issues = [];

        // 1. No orphaned PanelItems
        $orphanedPanelItems = PanelItem::whereNotNull('master_panel_item_id')
            ->whereDoesntHave('masterPanelItem')
            ->count();
        if ($orphanedPanelItems > 0) {
            $issues[] = "Orphaned PanelItems (missing MasterPanelItem): {$orphanedPanelItems}";
        }

        // 2. No orphaned Panels
        $orphanedPanels = Panel::whereNotNull('master_panel_id')
            ->whereDoesntHave('masterPanel')
            ->count();
        if ($orphanedPanels > 0) {
            $issues[] = "Orphaned Panels (missing MasterPanel): {$orphanedPanels}";
        }

        // 3. No duplicate PanelPanelItems (same panel_id + panel_item_id)
        $duplicatePPIs = DB::table('panel_panel_items')
            ->selectRaw('panel_id, panel_item_id, COUNT(*) as cnt')
            ->groupBy('panel_id', 'panel_item_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        if ($duplicatePPIs > 0) {
            $issues[] = "Duplicate PanelPanelItems: {$duplicatePPIs}";
        }

        // 4. Check for remaining MasterPanelItem duplicates
        $remainingItemDuplicates = DB::table('master_panel_items')
            ->selectRaw('LOWER(name) as normalized_name')
            ->selectRaw("LOWER(COALESCE(unit, '')) as normalized_unit")
            ->selectRaw('COUNT(*) as count')
            ->whereNull('deleted_at')
            ->groupByRaw("LOWER(name), LOWER(COALESCE(unit, ''))")
            ->havingRaw('COUNT(*) > 1')
            ->count();
        if ($remainingItemDuplicates > 0) {
            $issues[] = "Remaining MasterPanelItem duplicate groups: {$remainingItemDuplicates}";
        }

        // 5. Check for remaining MasterPanel duplicates (by name only - full check is expensive)
        $remainingPanelDuplicates = DB::table('master_panels')
            ->selectRaw('LOWER(name) as normalized_name')
            ->selectRaw('COUNT(*) as count')
            ->whereNull('deleted_at')
            ->groupByRaw('LOWER(name)')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        if ($remainingPanelDuplicates > 0) {
            $this->warn("  Note: {$remainingPanelDuplicates} MasterPanel name groups still have multiple entries (may have different item sets)");
        }

        if (empty($issues)) {
            $this->info("  All verification checks passed!");
        } else {
            $this->warn("  Verification issues found:");
            foreach ($issues as $issue) {
                $this->warn("    - {$issue}");
            }
        }
    }

    /**
     * Output a line only if verbose mode is enabled.
     */
    protected function verboseLine(string $message): void
    {
        if ($this->verboseOutput) {
            $this->line($message);
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Models\MasterPanelItem;
use App\Models\PanelItem;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateMissingMasterPanelItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'panel:create-missing-master-items
        {--dry-run : Preview changes without executing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create missing MasterPanelItems for PanelItems with mismatched units and reassign them';

    /**
     * Statistics tracking.
     *
     * @var array<string, int>
     */
    protected array $stats = [
        'master_panel_items_created' => 0,
        'master_panel_items_reused' => 0,
        'panel_items_updated' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $mode = $dryRun ? '[DRY-RUN]' : '[EXECUTE]';

        $this->info("{$mode} Creating missing MasterPanelItems and reassigning PanelItems...\n");

        Log::info('CreateMissingMasterPanelItems: Starting', ['dry_run' => $dryRun]);

        // Find all mismatched PanelItems
        $mismatches = $this->findMismatchedPanelItems();

        if ($mismatches->isEmpty()) {
            $this->info('No mismatched PanelItems found.');
            return self::SUCCESS;
        }

        $this->info("Found {$mismatches->count()} PanelItems with mismatched units:\n");

        try {
            DB::beginTransaction();

            foreach ($mismatches as $mismatch) {
                $this->processMismatch($mismatch, $dryRun);
            }

            if ($dryRun) {
                DB::rollBack();
                $this->info("\n[DRY-RUN] No changes were made.");
            } else {
                DB::commit();
                $this->info("\nChanges committed successfully.");
            }

            $this->displaySummary();

            Log::info('CreateMissingMasterPanelItems: Completed', $this->stats);

            return self::SUCCESS;

        } catch (Exception $e) {
            DB::rollBack();
            $this->error("Command failed: {$e->getMessage()}");
            Log::error('CreateMissingMasterPanelItems: Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Find PanelItems where unit doesn't match MasterPanelItem unit.
     */
    protected function findMismatchedPanelItems()
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
            ->orderBy('pi.id')
            ->get();
    }

    /**
     * Process a single mismatched PanelItem.
     */
    protected function processMismatch(object $mismatch, bool $dryRun): void
    {
        $panelItemId = $mismatch->panel_item_id;
        $panelItemName = $mismatch->panel_item_name;
        $panelItemUnit = $mismatch->panel_item_unit;
        $currentMasterItemId = $mismatch->master_panel_item_id;
        $currentMasterUnit = $mismatch->master_panel_item_unit ?? 'NULL';

        $displayUnit = $panelItemUnit ?? 'NULL';

        $this->line("PanelItem #{$panelItemId}: \"{$panelItemName}\" (unit: {$displayUnit})");
        $this->line("  Current: MasterPanelItem #{$currentMasterItemId} (unit: {$currentMasterUnit})");

        // Check if a MasterPanelItem with matching name and unit already exists
        $existingMaster = MasterPanelItem::whereRaw('LOWER(name) = ?', [strtolower($panelItemName)])
            ->whereRaw("LOWER(COALESCE(unit, '')) = ?", [strtolower($panelItemUnit ?? '')])
            ->whereNull('deleted_at')
            ->first();

        if ($existingMaster) {
            $this->info("  Found existing MasterPanelItem #{$existingMaster->id}: \"{$existingMaster->name}\" (unit: " . ($existingMaster->unit ?? 'NULL') . ")");

            if (!$dryRun) {
                PanelItem::where('id', $panelItemId)
                    ->update(['master_panel_item_id' => $existingMaster->id]);
            }

            $this->info("  -> " . ($dryRun ? 'Would update' : 'Updated') . " PanelItem #{$panelItemId} to MasterPanelItem #{$existingMaster->id}");
            $this->stats['master_panel_items_reused']++;
            $this->stats['panel_items_updated']++;
        } else {
            // Create new MasterPanelItem
            $newMasterId = null;

            if (!$dryRun) {
                $newMaster = MasterPanelItem::create([
                    'name' => $panelItemName,
                    'unit' => $panelItemUnit,
                ]);
                $newMasterId = $newMaster->id;

                PanelItem::where('id', $panelItemId)
                    ->update(['master_panel_item_id' => $newMasterId]);
            }

            $idDisplay = $dryRun ? '(new)' : "#{$newMasterId}";
            $this->warn("  Created MasterPanelItem {$idDisplay}: \"{$panelItemName}\" (unit: {$displayUnit})");
            $this->info("  -> " . ($dryRun ? 'Would update' : 'Updated') . " PanelItem #{$panelItemId} to MasterPanelItem {$idDisplay}");

            $this->stats['master_panel_items_created']++;
            $this->stats['panel_items_updated']++;
        }

        $this->line('');
    }

    /**
     * Display summary statistics.
     */
    protected function displaySummary(): void
    {
        $this->info("=== Summary ===");
        $this->info("MasterPanelItems created: {$this->stats['master_panel_items_created']}");
        $this->info("MasterPanelItems reused (already existed): {$this->stats['master_panel_items_reused']}");
        $this->info("PanelItems updated: {$this->stats['panel_items_updated']}");
    }
}

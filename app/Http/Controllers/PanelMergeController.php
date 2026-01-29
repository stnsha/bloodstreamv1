<?php

namespace App\Http\Controllers;

use App\Models\PanelMergeLog;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PanelMergeController extends Controller
{
    /**
     * Available commands configuration.
     */
    protected array $commands = [
        'panel:merge-duplicates' => [
            'name' => 'Merge Duplicate Master Panel Data',
            'description' => 'Merge duplicate MasterPanel and MasterPanelItem records with full cascade',
            'options' => [
                'dry-run' => 'Preview changes without executing',
                'items-only' => 'Only merge MasterPanelItems',
                'panels-only' => 'Only merge MasterPanels',
                'detailed' => 'Show detailed progress',
            ],
        ],
        'panel:fix-mismatched-references' => [
            'name' => 'Fix Mismatched References',
            'description' => 'Find and fix PanelItems where unit does not match their MasterPanelItem unit',
            'options' => [
                'dry-run' => 'Preview changes without executing',
                'fix' => 'Apply the fixes',
                'detailed' => 'Show detailed progress',
            ],
        ],
        'panel:create-missing-master-items' => [
            'name' => 'Create Missing MasterPanelItems',
            'description' => 'Create missing MasterPanelItems for PanelItems with mismatched units',
            'options' => [
                'dry-run' => 'Preview changes without executing',
            ],
        ],
    ];

    /**
     * Display the panel merge management page.
     */
    public function index(): View
    {
        $logs = PanelMergeLog::orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return view('panel-merge.index', [
            'commands' => $this->commands,
            'logs' => $logs,
        ]);
    }

    /**
     * Run a panel merge command.
     */
    public function run(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'command' => 'required|string|in:' . implode(',', array_keys($this->commands)),
                'options' => 'nullable|array',
            ]);
        } catch (Exception $e) {
            Log::error('PanelMergeController: Validation failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Validation failed: ' . $e->getMessage(),
            ], 422);
        }

        $command = $request->input('command');
        $options = $request->input('options', []);

        // Determine if this is a dry run
        $isDryRun = in_array('dry-run', $options);

        // Create log entry
        $log = PanelMergeLog::create([
            'command' => $command,
            'status' => 'running',
            'is_dry_run' => $isDryRun,
            'options' => $options,
            'started_at' => now(),
            'user_id' => auth()->id(),
        ]);

        Log::info('PanelMergeController: Running command', [
            'log_id' => $log->id,
            'command' => $command,
            'options' => $options,
        ]);

        try {
            // Build command options array
            $artisanOptions = [];
            foreach ($options as $option) {
                $artisanOptions["--{$option}"] = true;
            }

            // Pass the log ID so the command can record detailed changes
            $artisanOptions['--log-id'] = $log->id;

            // Run the command
            $exitCode = Artisan::call($command, $artisanOptions);
            $output = Artisan::output();

            // Parse stats from output if possible
            $stats = $this->parseStatsFromOutput($output);

            // Update log
            $log->update([
                'status' => $exitCode === 0 ? 'completed' : 'failed',
                'output' => $output,
                'stats' => $stats,
                'completed_at' => now(),
            ]);

            Log::info('PanelMergeController: Command completed', [
                'log_id' => $log->id,
                'exit_code' => $exitCode,
                'stats' => $stats,
            ]);

            return response()->json([
                'success' => $exitCode === 0,
                'log_id' => $log->id,
                'output' => $output,
                'stats' => $stats,
            ]);

        } catch (Exception $e) {
            $log->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            Log::error('PanelMergeController: Command failed', [
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get log details.
     */
    public function show(PanelMergeLog $log): JsonResponse
    {
        return response()->json([
            'log' => $log,
            'command_name' => $log->command_display_name,
            'duration' => $log->duration,
            'details_count' => $log->details()->count(),
        ]);
    }

    /**
     * Get detailed changes for a log.
     */
    public function details(PanelMergeLog $log, Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 50);
        $action = $request->input('action');
        $entityType = $request->input('entity_type');

        $query = $log->details()->orderBy('id', 'asc');

        if ($action) {
            $query->where('action', $action);
        }

        if ($entityType) {
            $query->where('entity_type', $entityType);
        }

        $details = $query->paginate($perPage);

        // Get summary counts by action
        $summaryCounts = $log->details()
            ->selectRaw('action, entity_type, COUNT(*) as count')
            ->groupBy('action', 'entity_type')
            ->get()
            ->groupBy('action')
            ->map(function ($items) {
                return $items->pluck('count', 'entity_type');
            });

        return response()->json([
            'details' => $details,
            'summary' => $summaryCounts,
        ]);
    }

    /**
     * Get logs history.
     */
    public function history(Request $request): JsonResponse
    {
        $logs = PanelMergeLog::orderBy('created_at', 'desc')
            ->limit($request->input('limit', 50))
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'command' => $log->command,
                    'command_name' => $log->command_display_name,
                    'status' => $log->status,
                    'status_badge_class' => $log->status_badge_class,
                    'is_dry_run' => $log->is_dry_run,
                    'options' => $log->options,
                    'stats' => $log->stats,
                    'duration' => $log->duration,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json(['logs' => $logs]);
    }

    /**
     * Parse statistics from command output.
     */
    protected function parseStatsFromOutput(string $output): array
    {
        $stats = [];

        // Extract key statistics using regex
        $patterns = [
            'master_panel_item_groups' => '/Groups processed:\s*(\d+)/i',
            'master_panel_items_deleted' => '/MasterPanelItems deleted:\s*(\d+)/i',
            'panel_items_merged' => '/PanelItems merged:\s*(\d+)/i',
            'panel_items_repointed' => '/PanelItems repointed:\s*(\d+)/i',
            'master_panel_groups' => '/MasterPanel Statistics:.*?Groups processed:\s*(\d+)/is',
            'master_panels_deleted' => '/MasterPanels deleted:\s*(\d+)/i',
            'panels_merged' => '/Panels merged:\s*(\d+)/i',
            'panels_repointed' => '/Panels repointed:\s*(\d+)/i',
            'panel_panel_items_merged' => '/PanelPanelItems merged:\s*(\d+)/i',
            'panel_panel_items_repointed' => '/PanelPanelItems repointed:\s*(\d+)/i',
            'test_result_items_updated' => '/TestResultItems updated:\s*(\d+)/i',
            'reference_ranges_merged' => '/ReferenceRanges merged:\s*(\d+)/i',
            'panel_interpretations_merged' => '/PanelInterpretations merged:\s*(\d+)/i',
            'mismatched_found' => '/Mismatched references found:\s*(\d+)/i',
            'fixable' => '/Fixable \(single match\):\s*(\d+)/i',
            'fixed' => '/Actually fixed:\s*(\d+)/i',
            'master_panel_items_created' => '/MasterPanelItems created:\s*(\d+)/i',
            'master_panel_items_reused' => '/MasterPanelItems reused.*?:\s*(\d+)/i',
            'panel_items_updated' => '/PanelItems updated:\s*(\d+)/i',
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $output, $matches)) {
                $stats[$key] = (int) $matches[1];
            }
        }

        return $stats;
    }
}

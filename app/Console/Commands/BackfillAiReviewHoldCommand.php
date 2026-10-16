<?php

namespace App\Console\Commands;

use App\Models\ClinicalCondition;
use App\Models\ConsultCallDetails;
use App\Models\TestResult;
use App\Services\TestResultCompletionDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackfillAiReviewHoldCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'consult-call:backfill-ai-review-hold
        {--include-closed : Also hold test results whose latest consult call detail is Closed}
        {--dry-run : Preview affected test results without updating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stamp ai_review_held_at on test results of already-enrolled consult calls '
        . '(CC / CC + AO / AO) that were never held, so "Release Doctor Review?" shows on the consult call edit page';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $includeClosed = (bool) $this->option('include-closed');

        Log::info('BackfillAiReviewHoldCommand: Starting', [
            'dry_run' => $dryRun,
            'include_closed' => $includeClosed,
        ]);

        // The edit page reads the hold flag off each consult call's latest detail,
        // so only that detail's test result is considered. whereHas('consultCall')
        // excludes soft-deleted consult calls.
        // Built as a subquery so MySQL resolves the latest detail IDs itself,
        // instead of round-tripping a large ID list through PHP bindings.
        $latestDetailIdsQuery = ConsultCallDetails::query()
            ->whereHas('consultCall')
            ->groupBy('consult_call_details.consult_call_id')
            ->selectRaw('MAX(consult_call_details.id)');

        $latestDetailCount = DB::query()->fromSub($latestDetailIdsQuery, 'latest')->count();

        $details = ConsultCallDetails::query()
            ->whereIn('id', $latestDetailIdsQuery->toBase())
            ->whereNotNull('test_result_id')
            ->whereNotNull('clinical_condition_id')
            ->when(! $includeClosed, function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('process_status')
                        ->orWhere('process_status', '!=', ConsultCallDetails::PROCESS_STATUS_CLOSED);
                });
            })
            ->get(['id', 'consult_call_id', 'test_result_id', 'clinical_condition_id']);

        // Read types straight from the table: getCondition() only knows active
        // conditions, and a consult call keeps its condition after it is deactivated.
        $conditionTypes = ClinicalCondition::query()
            ->whereIn('id', $details->pluck('clinical_condition_id')->unique())
            ->pluck('type', 'id');

        $testResultIds = $details
            ->filter(function (ConsultCallDetails $detail) use ($conditionTypes) {
                $type = $conditionTypes[$detail->clinical_condition_id] ?? null;

                return in_array($type, TestResultCompletionDispatcher::HOLD_CONDITION_TYPES, true);
            })
            ->pluck('test_result_id')
            ->unique()
            ->values();

        // Never hold a result twice, and never re-hold one already released.
        $targetIds = TestResult::query()
            ->whereIn('id', $testResultIds)
            ->whereNull('ai_review_held_at')
            ->whereNull('ai_review_released_at')
            ->pluck('id');

        // Per-stage counts so an empty result shows which filter removed everything.
        $unknownConditionIds = $details
            ->pluck('clinical_condition_id')
            ->unique()
            ->filter(fn ($conditionId) => ! isset($conditionTypes[$conditionId]))
            ->values();

        $this->line('Consult calls (latest detail):            ' . $latestDetailCount);
        $this->line('With test result + condition'
            . ($includeClosed ? ':             ' : ', not Closed: ') . $details->count());
        $this->line('Condition type CC / CC + AO / AO:          ' . $testResultIds->count());
        $this->line('Not yet held or released:                  ' . $targetIds->count());

        if ($unknownConditionIds->isNotEmpty()) {
            $this->warn('Condition ID(s) missing from clinical_conditions: '
                . $unknownConditionIds->implode(', '));
        }

        if ($targetIds->isEmpty()) {
            $this->info('No enrolled test results need an AI review hold.');

            Log::info('BackfillAiReviewHoldCommand: Nothing to update');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[DRY-RUN] Would stamp ai_review_held_at on {$targetIds->count()} test result(s).");
            $this->line($targetIds->implode(', '));

            return self::SUCCESS;
        }

        try {
            DB::beginTransaction();

            $updatedCount = TestResult::query()
                ->whereIn('id', $targetIds)
                ->whereNull('ai_review_held_at')
                ->whereNull('ai_review_released_at')
                ->update(['ai_review_held_at' => now()]);

            DB::commit();

            $this->info("Stamped ai_review_held_at on {$updatedCount} test result(s).");

            Log::info('BackfillAiReviewHoldCommand: Completed', [
                'updated_count' => $updatedCount,
                'test_result_ids' => $targetIds->all(),
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            $this->error('Failed to backfill AI review hold: ' . $e->getMessage());

            Log::error('BackfillAiReviewHoldCommand: Failed', [
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

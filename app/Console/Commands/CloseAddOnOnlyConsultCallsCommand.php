<?php

namespace App\Console\Commands;

use App\Jobs\SendToAIServer;
use App\Models\ConsultCall;
use App\Models\ConsultCallDetails;
use App\Models\TestResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CloseAddOnOnlyConsultCallsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'consult-call:close-add-on-only
        {--dry-run : Preview affected consult calls without updating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set Process Status to Closed on AO-only consult calls whose customer has already '
        . 'been called (Customer Response is not Pending), releasing any AI review still on hold';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        Log::info('CloseAddOnOnlyConsultCallsCommand: Starting', ['dry_run' => $dryRun]);

        // Advise type and process status are both read off each consult call's
        // latest detail, matching the consult call edit page. whereHas('consultCall')
        // excludes soft-deleted consult calls.
        $latestDetailIdsQuery = ConsultCallDetails::query()
            ->whereHas('consultCall')
            ->groupBy('consult_call_details.consult_call_id')
            ->selectRaw('MAX(consult_call_details.id)');

        $details = ConsultCallDetails::query()
            ->whereIn('consult_call_details.id', $latestDetailIdsQuery->toBase())
            ->join('clinical_conditions', 'clinical_conditions.id', '=', 'consult_call_details.clinical_condition_id')
            ->join('consult_calls', 'consult_calls.id', '=', 'consult_call_details.consult_call_id')
            ->where('clinical_conditions.type', 'AO')
            ->whereNotNull('consult_calls.consent_call_status')
            ->where('consult_calls.consent_call_status', '!=', ConsultCall::CONSENT_STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('consult_call_details.process_status')
                    ->orWhere('consult_call_details.process_status', '!=', ConsultCallDetails::PROCESS_STATUS_CLOSED);
            })
            ->get([
                'consult_call_details.id',
                'consult_call_details.consult_call_id',
                'consult_call_details.test_result_id',
                'consult_call_details.process_status',
                'consult_calls.consent_call_status',
            ]);

        // Closing a case releases an AI review that is still on hold (same rule as
        // ConsultCallController::releaseHeldAiReviewOnClose).
        $heldTestResultIds = TestResult::query()
            ->whereIn('id', $details->pluck('test_result_id')->filter()->unique())
            ->whereNotNull('ai_review_held_at')
            ->whereNull('ai_review_released_at')
            ->pluck('id');

        $this->line('AO-only, customer called, not Closed: ' . $details->count());
        $this->line('With AI review still on hold:         ' . $heldTestResultIds->count());

        if ($details->isEmpty()) {
            $this->info('No AO-only consult calls need closing.');

            Log::info('CloseAddOnOnlyConsultCallsCommand: Nothing to update');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->table(
                ['Consult Call', 'Detail', 'Customer Response', 'Process Status', 'Test Result', 'Review On Hold'],
                $details->map(fn (ConsultCallDetails $detail) => [
                    $detail->consult_call_id,
                    $detail->id,
                    $detail->consent_call_status,
                    $detail->process_status ?? 'NULL',
                    $detail->test_result_id ?? '-',
                    $heldTestResultIds->contains($detail->test_result_id) ? 'Yes' : 'No',
                ])->all()
            );
            $this->warn("[DRY-RUN] Would close {$details->count()} consult call(s) and release "
                . "{$heldTestResultIds->count()} held AI review(s).");

            return self::SUCCESS;
        }

        $detailIds = $details->pluck('id');
        $consultCallIds = $details->pluck('consult_call_id')->unique()->values();
        $releasedConsultCallIds = $details
            ->filter(fn (ConsultCallDetails $detail) => $heldTestResultIds->contains($detail->test_result_id))
            ->pluck('consult_call_id')
            ->unique()
            ->values();

        try {
            DB::beginTransaction();

            $closedCount = ConsultCallDetails::query()
                ->whereIn('id', $detailIds)
                ->update(['process_status' => ConsultCallDetails::PROCESS_STATUS_CLOSED]);

            $releasedCount = TestResult::query()
                ->whereIn('id', $heldTestResultIds)
                ->whereNotNull('ai_review_held_at')
                ->whereNull('ai_review_released_at')
                ->update(['ai_review_released_at' => now()]);

            if ($releasedConsultCallIds->isNotEmpty()
                && Schema::hasColumn('consult_call_details', 'release_doctor_review')) {
                ConsultCallDetails::query()
                    ->whereIn('consult_call_id', $releasedConsultCallIds)
                    ->update(['release_doctor_review' => true]);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            $this->error('Failed to close AO-only consult calls: ' . $e->getMessage());

            Log::error('CloseAddOnOnlyConsultCallsCommand: Failed', [
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        // Dispatch only after commit, so the job never sees an unreleased row.
        foreach ($heldTestResultIds as $testResultId) {
            SendToAIServer::dispatch($testResultId);
        }

        $this->info("Closed {$closedCount} consult call(s); released {$releasedCount} held AI review(s).");

        Log::info('CloseAddOnOnlyConsultCallsCommand: Completed', [
            'closed_count' => $closedCount,
            'released_count' => $releasedCount,
            'consult_call_ids' => $consultCallIds->all(),
            'released_test_result_ids' => $heldTestResultIds->all(),
        ]);

        return self::SUCCESS;
    }
}

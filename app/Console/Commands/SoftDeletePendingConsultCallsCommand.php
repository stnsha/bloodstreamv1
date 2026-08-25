<?php

namespace App\Console\Commands;

use App\Models\ConsultCall;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SoftDeletePendingConsultCallsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'consult-call:soft-delete-pending
        {--from-date= : Soft delete pending consult calls whose linked test result collected_date is on or before this date (format: d/m/Y or d/m, e.g. 31/07 or 31/07/2026)}
        {--dry-run : Preview affected rows without deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Soft delete consult_calls with consent_call_status still pending (0), '
        . 'where the collected_date of the test result on their consult_call_details is on or before --from-date';

    public function handle(): int
    {
        $fromDateOption = $this->option('from-date');

        if (empty($fromDateOption)) {
            $this->error('The --from-date option is required (format: d/m/Y or d/m).');

            return self::FAILURE;
        }

        $cutoff = $this->parseCutoffDate($fromDateOption);

        if ($cutoff === null) {
            $this->error("Could not parse --from-date value \"{$fromDateOption}\". Expected format: d/m/Y or d/m.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        Log::info('SoftDeletePendingConsultCallsCommand: Starting', [
            'from_date_option' => $fromDateOption,
            'cutoff' => $cutoff->toDateTimeString(),
            'dry_run' => $dryRun,
        ]);

        $query = ConsultCall::query()
            ->where('consent_call_status', ConsultCall::CONSENT_STATUS_PENDING)
            ->whereHas('details', function ($detailsQuery) use ($cutoff) {
                $detailsQuery->whereHas('testResult', function ($testResultQuery) use ($cutoff) {
                    $testResultQuery->where('collected_date', '<=', $cutoff);
                });
            });

        $ids = $query->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('No pending consult calls found with collected_date on or before '
                . $cutoff->toDateString() . '.');

            Log::info('SoftDeletePendingConsultCallsCommand: Nothing to delete', [
                'cutoff' => $cutoff->toDateTimeString(),
            ]);

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[DRY-RUN] Would soft delete {$ids->count()} consult call(s) with collected_date on or before "
                . $cutoff->toDateString() . '.');
            $this->line($ids->implode(', '));

            return self::SUCCESS;
        }

        try {
            DB::beginTransaction();

            $deletedCount = ConsultCall::whereIn('id', $ids)->delete();

            DB::commit();

            $this->info("Soft deleted {$deletedCount} pending consult call(s) with collected_date on or before "
                . $cutoff->toDateString() . '.');

            Log::info('SoftDeletePendingConsultCallsCommand: Completed', [
                'cutoff' => $cutoff->toDateTimeString(),
                'deleted_count' => $deletedCount,
                'consult_call_ids' => $ids->all(),
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            $this->error('Failed to soft delete pending consult calls: ' . $e->getMessage());

            Log::error('SoftDeletePendingConsultCallsCommand: Failed', [
                'cutoff' => $cutoff->toDateTimeString(),
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Accepts d/m/Y or d/m (year defaults to current year). Returns the cutoff
     * as end-of-day so "31/07" includes every collected_date on 2026-07-31.
     */
    private function parseCutoffDate(string $value): ?Carbon
    {
        $value = trim($value);

        foreach (['d/m/Y', 'd/m'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                if ($date !== false) {
                    return $date->endOfDay();
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        return null;
    }
}

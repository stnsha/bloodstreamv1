<?php

namespace App\Console\Commands;

use App\Models\ConsultCallDetails;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MigrateConsultationTypeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'consult-call:migrate-consultation-type
        {--dry-run : Preview affected rows without writing to the database}
        {--force : Also overwrite rows that already have a consultation_type set (default: only fill null values)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill consult_call_details.consultation_type: for each consult_call_id, '
        . 'the earliest detail row (by id) is New Case, every later detail row for that same '
        . 'consult_call_id is Follow Up';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        // Scan every detail row in ascending id order (regardless of its current
        // consultation_type) so "first seen for this consult_call_id" is always
        // correct, even when --force is off and earlier siblings get skipped below.
        $seenCallIds = [];
        $newCaseCount = 0;
        $followUpCount = 0;
        $skippedCount = 0;

        ConsultCallDetails::query()
            ->select('id', 'consult_call_id', 'consultation_type')
            ->chunkById(1000, function ($details) use (&$seenCallIds, &$newCaseCount, &$followUpCount, &$skippedCount, $force, $dryRun) {
                $newCaseIds = [];
                $followUpIds = [];

                foreach ($details as $detail) {
                    $isFirstForCall = !isset($seenCallIds[$detail->consult_call_id]);
                    $seenCallIds[$detail->consult_call_id] = true;

                    if (!$force && $detail->consultation_type !== null) {
                        $skippedCount++;
                        continue;
                    }

                    if ($isFirstForCall) {
                        $newCaseIds[] = $detail->id;
                    } else {
                        $followUpIds[] = $detail->id;
                    }
                }

                $newCaseCount += count($newCaseIds);
                $followUpCount += count($followUpIds);

                if ($dryRun) {
                    return;
                }

                if ($newCaseIds) {
                    ConsultCallDetails::whereIn('id', $newCaseIds)
                        ->update(['consultation_type' => ConsultCallDetails::CONSULTATION_TYPE_NEW_CASE]);
                }

                if ($followUpIds) {
                    ConsultCallDetails::whereIn('id', $followUpIds)
                        ->update(['consultation_type' => ConsultCallDetails::CONSULTATION_TYPE_FOLLOW_UP]);
                }
            });

        $total = $newCaseCount + $followUpCount;

        if ($total === 0) {
            $this->info("No consult_call_details rows need backfilling ({$skippedCount} already set). Nothing to do.");

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[DRY-RUN] Would update {$total} row(s): {$newCaseCount} to New Case, "
                . "{$followUpCount} to Follow Up ({$skippedCount} skipped, already set). No changes made.");

            return self::SUCCESS;
        }

        $this->info("Updated {$total} row(s): {$newCaseCount} New Case, {$followUpCount} Follow Up "
            . "({$skippedCount} skipped, already set).");

        Log::info('MigrateConsultationTypeCommand: Completed', [
            'rows_updated' => $total,
            'new_case' => $newCaseCount,
            'follow_up' => $followUpCount,
            'skipped_already_set' => $skippedCount,
            'force' => $force,
        ]);

        return self::SUCCESS;
    }
}

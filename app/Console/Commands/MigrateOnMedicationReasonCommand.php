<?php

namespace App\Console\Commands;

use App\Models\ConsultCall;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MigrateOnMedicationReasonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'consult-call:migrate-on-medication-reason
        {--dry-run : Preview affected rows without writing to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill the new consult_calls.reason column for existing consent_call_status = 3 '
        . '(formerly "On Prescribed Medication", now "Others") records with the legacy reason text';

    /**
     * Legacy consent status label, now stored in the reason column instead.
     *
     * @var string
     */
    protected const LEGACY_REASON = 'On Prescribed Medication/Follow-Up';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = ConsultCall::where('consent_call_status', ConsultCall::CONSENT_STATUS_OTHERS)
            ->whereNull('reason');

        $count = $query->count();

        if ($count === 0) {
            $this->info('No consult_calls rows need backfilling. Nothing to do.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[DRY-RUN] Would set reason = '" . self::LEGACY_REASON . "' on {$count} row(s) "
                . '(consent_call_status = 3, reason currently null). No changes made.');

            return self::SUCCESS;
        }

        $updated = $query->update(['reason' => self::LEGACY_REASON]);

        $this->info("Updated {$updated} row(s): reason = '" . self::LEGACY_REASON . "'.");

        Log::info('MigrateOnMedicationReasonCommand: Completed', ['rows_updated' => $updated]);

        return self::SUCCESS;
    }
}

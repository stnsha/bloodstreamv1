<?php

namespace App\Console\Commands;

use App\Exceptions\OdbInsytePushException;
use App\Models\Patient;
use App\Services\OdbInsytePushService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class PushPatientToOdbInsyteCommand extends Command
{
    protected $signature = 'odb:insyte-push
        {icno : Patient IC number}
        {--dry-run : Print the payload without pushing to ODB}';

    protected $description = 'Push one patient (2 years of blood tests, MyHealth check-record, doctor remarks) to the ODB InSyte Push API';

    public function handle(OdbInsytePushService $pushService): int
    {
        $icno = (string) $this->argument('icno');
        $dryRun = (bool) $this->option('dry-run');

        Log::channel('odb-push')->info('odb:insyte-push: starting', ['icno' => $icno, 'dry_run' => $dryRun]);

        $patient = Patient::where('icno', $icno)->first();

        if (! $patient) {
            $this->error("No patient found for IC {$icno}.");
            Log::channel('odb-push')->warning('odb:insyte-push: patient not found', ['icno' => $icno]);

            return self::FAILURE;
        }

        try {
            $payload = $pushService->buildPayload($patient);

            if ($payload === null) {
                $this->warn('Patient has no blood_test or check_record data in the history window. Nothing to push.');

                return self::SUCCESS;
            }

            if ($dryRun) {
                $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                Log::channel('odb-push')->info('odb:insyte-push: dry run completed', ['icno' => $icno]);

                return self::SUCCESS;
            }

            $response = $pushService->push($payload);

            $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            Log::channel('odb-push')->info('odb:insyte-push: completed', ['icno' => $icno]);

            return self::SUCCESS;
        } catch (OdbInsytePushException $e) {
            $this->error($e->getMessage());
            Log::channel('odb-push')->error('odb:insyte-push: push failed', [
                'icno' => $icno,
                'http_status' => $e->httpStatus,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            Log::channel('odb-push')->error('odb:insyte-push: unexpected failure', [
                'icno' => $icno,
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return self::FAILURE;
        }
    }
}

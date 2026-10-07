<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Services\MyHealthService;
use App\Services\OdbInsytePushService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Picks random patients that have both blood test results (any lab) and MyHealth
 * check_record history spread across the InSyte history window (default 2 years),
 * builds the exact receive_and_trigger.php payload via OdbInsytePushService::buildPayload()
 * and writes each one to a JSON file. Doctor remarks are excluded (past readings only).
 *
 * Read-only: nothing is pushed to ODB, ODB is not queried, and no local DB rows are written.
 */
class GenerateOdbInsyteSamplePayloads extends Command
{
    private const LOG_CHANNEL = 'odb-push';

    protected $signature = 'odb:insyte-sample-payloads
        {--count=5 : Number of JSON files to generate}
        {--min-span-days=365 : Minimum days between oldest and newest reading, applied to blood_test and check_record separately}
        {--min-labs=1 : Minimum number of distinct labs in the blood test history}
        {--pool=300 : Number of random candidate patients to evaluate}
        {--output= : Output directory (default: storage/app/odb-insyte-samples/{timestamp})}';

    protected $description = 'Write sample ODB InSyte push payloads (JSON) for random patients with full-window blood test and MyHealth history. No push is sent.';

    public function handle(OdbInsytePushService $pushService, MyHealthService $myHealthService): int
    {
        $count = max(1, (int) $this->option('count'));
        $minSpanDays = max(0, (int) $this->option('min-span-days'));
        $minLabs = max(1, (int) $this->option('min-labs'));
        $pool = max($count, (int) $this->option('pool'));
        $historyYears = max(1, (int) config('services.odb_insyte.history_years', 2));
        $cutoff = now()->subYears($historyYears)->startOfDay();
        $outputDir = $this->option('output') ?: storage_path('app/odb-insyte-samples/'.now()->format('Ymd_His'));

        $context = [
            'count' => $count,
            'min_span_days' => $minSpanDays,
            'min_labs' => $minLabs,
            'pool' => $pool,
            'history_years' => $historyYears,
            'cutoff' => $cutoff->toDateString(),
            'output' => $outputDir,
        ];

        Log::channel(self::LOG_CHANNEL)->info('odb:insyte-sample-payloads: starting', $context);
        $this->info("Window: {$cutoff->toDateString()} to today ({$historyYears} years). Looking for {$count} patients.");

        try {
            $candidates = $this->findCandidates($cutoff, $minSpanDays, $minLabs, $pool);
        } catch (Throwable $e) {
            $this->error('Candidate query failed: '.$e->getMessage());
            Log::channel(self::LOG_CHANNEL)->error('odb:insyte-sample-payloads: candidate query failed', $context + [
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        $this->line("Blood test candidates found: {$candidates->count()}");

        if ($candidates->isEmpty()) {
            $this->warn('No patient has blood test history matching the criteria.');
            Log::channel(self::LOG_CHANNEL)->warning('odb:insyte-sample-payloads: no blood test candidates', $context);

            return self::SUCCESS;
        }

        File::ensureDirectoryExists($outputDir);

        $written = [];
        $checked = 0;

        foreach ($candidates as $candidate) {
            if (count($written) >= $count) {
                break;
            }

            $checked++;

            try {
                // Cheap MyHealth pre-check before building the full payload.
                $records = $myHealthService->getCheckRecordsByICSince($candidate->icno, $cutoff);
                if ($records->isEmpty() || $this->spanDays($records->pluck('date_time')->all()) < $minSpanDays) {
                    continue;
                }

                $patient = Patient::find($candidate->id);
                if (! $patient) {
                    continue;
                }

                $payload = $pushService->buildPayload($patient, includeRemarks: false);
                if ($payload === null) {
                    continue;
                }

                // Verify against the payload itself, which is what ODB would receive.
                $bloodTestDates = array_column($payload['blood_test'], 'collected_date');
                $checkRecordDates = [];
                foreach ((array) $payload['check_record'] as $readings) {
                    foreach ($readings as $reading) {
                        $checkRecordDates[] = $reading['date_time'];
                    }
                }

                $bloodTestSpan = $this->spanDays($bloodTestDates);
                $checkRecordSpan = $this->spanDays($checkRecordDates);

                if (empty($bloodTestDates) || empty($checkRecordDates)
                    || $bloodTestSpan < $minSpanDays || $checkRecordSpan < $minSpanDays) {
                    continue;
                }

                $index = count($written) + 1;
                $file = $outputDir.DIRECTORY_SEPARATOR.sprintf('insyte_payload_%d_patient_%d.json', $index, $patient->id);

                File::put($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                $summary = [
                    'file' => basename($file),
                    'patient_id' => $patient->id,
                    'labs' => $candidate->labs,
                    'blood_test_reports' => count($payload['blood_test']),
                    'blood_test_range' => min($bloodTestDates).' to '.max($bloodTestDates),
                    'check_record_parameters' => count((array) $payload['check_record']),
                    'check_record_range' => mb_substr(min($checkRecordDates), 0, 10).' to '.mb_substr(max($checkRecordDates), 0, 10),
                ];

                $written[] = $summary;

                Log::channel(self::LOG_CHANNEL)->info('odb:insyte-sample-payloads: payload written', $summary);
                $this->info("[{$index}/{$count}] {$summary['file']}");
            } catch (Throwable $e) {
                $this->warn("Skipped patient {$candidate->id}: {$e->getMessage()}");
                Log::channel(self::LOG_CHANNEL)->error('odb:insyte-sample-payloads: candidate failed, skipped', [
                    'patient_id' => $candidate->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile().':'.$e->getLine(),
                ]);
            }
        }

        if (! empty($written)) {
            $this->newLine();
            $this->table(array_keys($written[0]), array_map('array_values', $written));
        }

        $this->line("Candidates checked: {$checked}. Files written: ".count($written)." in {$outputDir}");

        if (count($written) < $count) {
            $this->warn('Fewer files than requested. Try a larger --pool or a smaller --min-span-days.');
            Log::channel(self::LOG_CHANNEL)->warning('odb:insyte-sample-payloads: fewer payloads than requested', $context + [
                'written' => count($written),
                'checked' => $checked,
            ]);
        }

        Log::channel(self::LOG_CHANNEL)->info('odb:insyte-sample-payloads: completed', $context + [
            'written' => count($written),
            'checked' => $checked,
        ]);

        return self::SUCCESS;
    }

    /**
     * Random patients whose blood test results in the window span at least
     * $minSpanDays, across all labs (lab resolved via doctors.lab_id).
     */
    private function findCandidates(Carbon $cutoff, int $minSpanDays, int $minLabs, int $pool)
    {
        $query = DB::table('test_results as tr')
            ->join('patients as p', 'p.id', '=', 'tr.patient_id')
            ->leftJoin('doctors as d', 'd.id', '=', 'tr.doctor_id')
            ->leftJoin('labs as l', 'l.id', '=', 'd.lab_id')
            ->whereNull('tr.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('tr.collected_date', '>=', $cutoff)
            ->whereNotNull('p.icno')
            ->where('p.icno', '!=', '')
            ->groupBy('p.id', 'p.icno')
            ->selectRaw('p.id, p.icno, COUNT(*) AS reports, COUNT(DISTINCT d.lab_id) AS lab_count, GROUP_CONCAT(DISTINCT l.name) AS labs')
            ->havingRaw('DATEDIFF(MAX(tr.collected_date), MIN(tr.collected_date)) >= ?', [$minSpanDays]);

        if ($minLabs > 1) {
            $query->havingRaw('COUNT(DISTINCT d.lab_id) >= ?', [$minLabs]);
        }

        return $query->inRandomOrder()->limit($pool)->get();
    }

    private function spanDays(array $dates): int
    {
        $dates = array_filter($dates);
        if (count($dates) < 2) {
            return 0;
        }

        return (int) Carbon::parse(min($dates))->diffInDays(Carbon::parse(max($dates)));
    }
}

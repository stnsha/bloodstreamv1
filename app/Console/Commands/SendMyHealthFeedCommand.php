<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Models\TestResult;
use App\Services\MyHealthFeedService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Manual test tool for the MyHealth lab feed. Builds the same payload as
 * SendToMyHealthFeed for a patient IC number and POSTs it synchronously,
 * printing the response.
 *
 * Unlike the job it ignores is_reviewed / the MYHEALTH_FEED_FROM date and
 * does not write to the database unless --mark-reviewed is given.
 */
class SendMyHealthFeedCommand extends Command
{
    protected $signature = 'myhealth:send-feed
        {icno : IC number of the patient to send}
        {--dry-run : Build and save the payload without sending it}
        {--mark-reviewed : Set is_reviewed=1 on the latest test result in the feed when MyHealth accepts it}';

    protected $description = 'Send one patient to the MyHealth lab feed by IC number (testing tool)';

    public function handle(MyHealthFeedService $feedService): int
    {
        $icno = trim((string) $this->argument('icno'));
        $dryRun = (bool) $this->option('dry-run');

        Log::channel('myhealth-feed')->info('myhealth:send-feed: starting', [
            'icno' => $icno,
            'dry_run' => $dryRun,
        ]);

        $patient = Patient::where('icno', $icno)->first();

        if (! $patient) {
            $this->error("No patient found with icno {$icno}.");
            Log::channel('myhealth-feed')->warning('myhealth:send-feed: patient not found', ['icno' => $icno]);

            return self::FAILURE;
        }

        $this->line('Patient: '.$patient->id.' / icno '.$icno.' / '.($patient->name ?? 'null'));

        try {
            $payload = $feedService->buildPayloadForIcno($icno);
        } catch (Throwable $e) {
            $this->error('Failed to build payload: '.$e->getMessage());
            Log::channel('myhealth-feed')->error('myhealth:send-feed: failed to build payload', [
                'icno' => $icno,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        // Test results are ordered by collected_date descending, so the first is the latest.
        $latestTestResult = $payload['body']['feed']['data']['test_results'][0]['test_result'] ?? null;

        $json = json_encode($payload['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $payloadPath = "myhealth-feed/icno-{$icno}.json";
        Storage::disk('local')->put($payloadPath, $json);

        $this->line('Results in feed (last '.MyHealthFeedService::FEED_HISTORY_YEARS.' years): '.$payload['stats']['test_result_count']);

        if ($latestTestResult) {
            $this->line('Latest result: '.$latestTestResult->id.' (lab_no '.($latestTestResult->lab_no ?? 'null')
                .', is_completed '.($latestTestResult->is_completed ? 'true' : 'false')
                .', is_reviewed '.($latestTestResult->is_reviewed ? 'true' : 'false').')');
        }

        $this->line('Payload size: '.round(strlen($json) / 1024, 1).' KB');
        $this->line('Payload saved: '.Storage::disk('local')->path($payloadPath));
        $this->line('');

        if ($dryRun) {
            $this->info('Dry run: payload not sent.');

            return self::SUCCESS;
        }

        $this->line('POST '.config('services.myhealth_feed.url'));

        try {
            $response = $feedService->send($payload['body']);
        } catch (Throwable $e) {
            $this->error('Request failed: '.$e->getMessage());
            Log::channel('myhealth-feed')->error('myhealth:send-feed: request failed', [
                'icno' => $icno,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        $this->line('HTTP '.$response->status());
        $this->line($response->body());
        $this->line('');

        if (! $feedService->isAccepted($response)) {
            $this->error('MyHealth rejected the feed.');
            Log::channel('myhealth-feed')->error('myhealth:send-feed: feed rejected', [
                'icno' => $icno,
                'http_status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 1000),
            ]);

            return self::FAILURE;
        }

        $this->info('MyHealth accepted the feed.');
        Log::channel('myhealth-feed')->info('myhealth:send-feed: feed accepted', [
            'icno' => $icno,
            'http_status' => $response->status(),
        ]);

        if ($this->option('mark-reviewed')) {
            if (! $latestTestResult) {
                $this->warn('No test result in the feed to mark reviewed.');

                return self::SUCCESS;
            }

            try {
                DB::beginTransaction();

                TestResult::whereKey($latestTestResult->id)->update(['is_reviewed' => true]);

                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                $this->error('Failed to mark test result reviewed: '.$e->getMessage());
                Log::channel('myhealth-feed')->error('myhealth:send-feed: failed to mark reviewed', [
                    'icno' => $icno,
                    'test_result_id' => $latestTestResult->id,
                    'error' => $e->getMessage(),
                ]);

                return self::FAILURE;
            }

            $this->info('Test result '.$latestTestResult->id.' marked is_reviewed=1.');
            Log::channel('myhealth-feed')->info('myhealth:send-feed: marked reviewed', [
                'icno' => $icno,
                'test_result_id' => $latestTestResult->id,
            ]);
        }

        return self::SUCCESS;
    }
}

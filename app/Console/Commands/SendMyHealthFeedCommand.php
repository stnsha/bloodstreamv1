<?php

namespace App\Console\Commands;

use App\Models\TestResult;
use App\Services\MyHealthFeedService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Manual test tool for the MyHealth lab feed. Builds the same payload as
 * SendToMyHealthFeed and POSTs it synchronously, printing the response.
 *
 * Unlike the job it ignores is_reviewed / the MYHEALTH_FEED_FROM date and
 * does not write to the database unless --mark-reviewed is given.
 */
class SendMyHealthFeedCommand extends Command
{
    protected $signature = 'myhealth:send-feed
        {test_result_id : ID of the test result to send}
        {--dry-run : Build and save the payload without sending it}
        {--mark-reviewed : Set is_reviewed=1 on the test result when MyHealth accepts the feed}';

    protected $description = 'Send one test result to the MyHealth lab feed (testing tool)';

    public function handle(MyHealthFeedService $feedService): int
    {
        $testResultId = (int) $this->argument('test_result_id');
        $dryRun = (bool) $this->option('dry-run');

        Log::channel('myhealth-feed')->info('myhealth:send-feed: starting', [
            'test_result_id' => $testResultId,
            'dry_run' => $dryRun,
        ]);

        $testResult = TestResult::with('patient')->find($testResultId);

        if (! $testResult) {
            $this->error("Test result {$testResultId} not found.");

            return self::FAILURE;
        }

        $this->line('Test result : '.$testResult->id.' (lab_no '.($testResult->lab_no ?? 'null').')');
        $this->line('Patient     : '.($testResult->patient_id ?? 'null').' / icno '.($testResult->patient->icno ?? 'null'));
        $this->line('is_completed: '.($testResult->is_completed ? 'true' : 'false')
            .', is_reviewed: '.($testResult->is_reviewed ? 'true' : 'false'));
        $this->line('');

        try {
            $payload = $feedService->buildPayload($testResult);
        } catch (Throwable $e) {
            $this->error('Failed to build payload: '.$e->getMessage());
            Log::channel('myhealth-feed')->error('myhealth:send-feed: failed to build payload', [
                'test_result_id' => $testResultId,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        $json = json_encode($payload['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $payloadPath = "myhealth-feed/test-result-{$testResultId}.json";
        Storage::disk('local')->put($payloadPath, $json);

        $this->line('Results in feed (last '.MyHealthFeedService::FEED_HISTORY_YEARS.' years): '.$payload['stats']['test_result_count']);
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
                'test_result_id' => $testResultId,
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
                'test_result_id' => $testResultId,
                'http_status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 1000),
            ]);

            return self::FAILURE;
        }

        $this->info('MyHealth accepted the feed.');
        Log::channel('myhealth-feed')->info('myhealth:send-feed: feed accepted', [
            'test_result_id' => $testResultId,
            'http_status' => $response->status(),
        ]);

        if ($this->option('mark-reviewed')) {
            try {
                DB::beginTransaction();

                TestResult::whereKey($testResultId)->update(['is_reviewed' => true]);

                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                $this->error('Failed to mark test result reviewed: '.$e->getMessage());
                Log::channel('myhealth-feed')->error('myhealth:send-feed: failed to mark reviewed', [
                    'test_result_id' => $testResultId,
                    'error' => $e->getMessage(),
                ]);

                return self::FAILURE;
            }

            $this->info('Test result marked is_reviewed=1.');
            Log::channel('myhealth-feed')->info('myhealth:send-feed: marked reviewed', [
                'test_result_id' => $testResultId,
            ]);
        }

        return self::SUCCESS;
    }
}

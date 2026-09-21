<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAIWebhookResult;
use App\Jobs\SendToAIServer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ReflectionProperty;
use Throwable;

class AutoRetryFailedJobs extends Command
{
    /**
     * Only job classes verified safe to re-dispatch without side effects —
     * ShouldBeUnique plus their own self-guarding idempotency checks
     * (TestResult state, AIReview processing_status, webhook idempotency
     * key). Adding a class here without verifying it is idempotent can
     * duplicate data or resend external requests; everything else is left
     * in failed_jobs for manual review via `queue:failed`.
     */
    private const RETRYABLE_JOB_CLASSES = [
        SendToAIServer::class,
        ProcessAIWebhookResult::class,
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:auto-retry-failed
                            {--hours=24 : Only consider jobs that failed within the last N hours}
                            {--limit=100 : Maximum number of jobs to retry per run}
                            {--max-attempts=3 : Maximum auto-retry attempts per underlying test result, per rolling --hours window}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-retry failed_jobs entries for an allowlisted set of known-idempotent job classes; everything else is left for manual review';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $limit = (int) $this->option('limit');
        $maxAttempts = (int) $this->option('max-attempts');

        Log::channel('job')->info('AutoRetryFailedJobs: started', [
            'hours' => $hours,
            'limit' => $limit,
            'max_attempts' => $maxAttempts,
        ]);

        try {
            $candidates = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subHours($hours))
                ->orderBy('failed_at')
                ->get();

            $retried = 0;
            $skippedNotAllowlisted = 0;
            $skippedAttemptsExhausted = 0;
            $skippedUnparseable = 0;

            foreach ($candidates as $row) {
                if ($retried >= $limit) {
                    break;
                }

                $jobClass = $this->resolveJobClass($row->payload);

                if (! $jobClass || ! in_array($jobClass, self::RETRYABLE_JOB_CLASSES, true)) {
                    $skippedNotAllowlisted++;
                    continue;
                }

                $retryKey = $this->resolveRetryKey($jobClass, $row->payload);

                if (! $retryKey) {
                    $skippedUnparseable++;
                    continue;
                }

                $attemptCacheKey = "queue_auto_retry:{$retryKey}";
                $attempts = (int) Cache::get($attemptCacheKey, 0);

                if ($attempts >= $maxAttempts) {
                    $skippedAttemptsExhausted++;
                    continue;
                }

                try {
                    Cache::put($attemptCacheKey, $attempts + 1, now()->addHours($hours));

                    Artisan::call('queue:retry', ['id' => [$row->uuid]]);

                    $retried++;

                    Log::channel('job')->info('AutoRetryFailedJobs: retried job', [
                        'uuid' => $row->uuid,
                        'job_class' => $jobClass,
                        'retry_key' => $retryKey,
                        'attempt' => $attempts + 1,
                    ]);
                } catch (Throwable $e) {
                    Log::channel('job')->error('AutoRetryFailedJobs: failed to retry job', [
                        'uuid' => $row->uuid,
                        'job_class' => $jobClass,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->info("Retried: {$retried}, skipped (not allowlisted): {$skippedNotAllowlisted}, skipped (attempts exhausted): {$skippedAttemptsExhausted}, skipped (unparseable): {$skippedUnparseable}");

            Log::channel('job')->info('AutoRetryFailedJobs: completed', [
                'retried' => $retried,
                'skipped_not_allowlisted' => $skippedNotAllowlisted,
                'skipped_attempts_exhausted' => $skippedAttemptsExhausted,
                'skipped_unparseable' => $skippedUnparseable,
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            Log::channel('job')->error('AutoRetryFailedJobs: command failed', [
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Extract the job's display class name from its failed_jobs payload.
     */
    private function resolveJobClass(string $rawPayload): ?string
    {
        $payload = json_decode($rawPayload, true);

        return $payload['displayName'] ?? null;
    }

    /**
     * Build a stable retry-tracking key from the job's underlying test_result_id
     * rather than the failed_jobs uuid — queue:retry deletes the row and
     * re-dispatches with a brand-new uuid on every attempt, so a uuid-keyed
     * counter would never accumulate and the attempts cap would never bite.
     */
    private function resolveRetryKey(string $jobClass, string $rawPayload): ?string
    {
        try {
            $payload = json_decode($rawPayload, true);
            $serializedCommand = $payload['data']['command'] ?? null;

            if (! $serializedCommand) {
                return null;
            }

            $command = unserialize($serializedCommand);

            // testResultId (SendToAIServer) is public; webhookData (ProcessAIWebhookResult)
            // is protected, so read both via reflection rather than relying on visibility.
            $propertyName = match ($jobClass) {
                SendToAIServer::class => 'testResultId',
                ProcessAIWebhookResult::class => 'webhookData',
                default => null,
            };

            if (! $propertyName) {
                return null;
            }

            $property = new ReflectionProperty($command, $propertyName);
            $property->setAccessible(true);
            $value = $property->getValue($command);

            $testResultId = is_array($value) ? ($value['test_result_id'] ?? null) : $value;

            return $testResultId ? "{$jobClass}:{$testResultId}" : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

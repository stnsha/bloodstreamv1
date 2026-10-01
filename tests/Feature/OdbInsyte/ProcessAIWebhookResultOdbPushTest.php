<?php

namespace Tests\Feature\OdbInsyte;

use App\Jobs\ProcessAIWebhookResult;
use App\Jobs\PushPatientToOdbInsyte;
use App\Models\AIReview;
use App\Models\TestResult;
use App\Services\ReviewHtmlGenerator;
use App\Services\TestResultCompilerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * ProcessAIWebhookResult must queue the ODB InSyte push only when the review
 * actually transitions to COMPLETED. Uses DatabaseTransactions against the
 * existing dev database (never RefreshDatabase here).
 */
class ProcessAIWebhookResultOdbPushTest extends TestCase
{
    use DatabaseTransactions;

    private TestResult $testResult;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([PushPatientToOdbInsyte::class]);
        config(['services.odb_insyte.enabled' => true]);

        $testResult = TestResult::whereNotNull('patient_id')->first();
        if (! $testResult) {
            $this->markTestSkipped('No test_results row available in the dev database.');
        }
        $this->testResult = $testResult;

        AIReview::where('test_result_id', $testResult->id)->forceDelete();
        AIReview::create([
            'test_result_id' => $testResult->id,
            'processing_status' => 'PENDING',
            'compiled_results' => ['test' => true],
        ]);
    }

    private function webhook(bool $success = true, string $status = 'DONE'): array
    {
        return [
            'test_result_id' => $this->testResult->id,
            'success' => $success,
            'status' => $status,
            'data' => ['ai_analysis' => ['status' => 200, 'answer' => ['section_a1' => []]]],
        ];
    }

    private function runJob(array $webhook): void
    {
        (new ProcessAIWebhookResult($webhook))->handle(
            app(ReviewHtmlGenerator::class),
            app(TestResultCompilerService::class)
        );
    }

    public function test_completed_review_dispatches_push(): void
    {
        $this->runJob($this->webhook());

        $this->assertSame('COMPLETED', AIReview::where('test_result_id', $this->testResult->id)->latest('id')->value('processing_status'));
        Queue::assertPushed(PushPatientToOdbInsyte::class, fn ($job) => $job->testResultId === $this->testResult->id);
    }

    public function test_replayed_webhook_does_not_dispatch_again(): void
    {
        $this->runJob($this->webhook());
        $this->runJob($this->webhook());

        Queue::assertPushed(PushPatientToOdbInsyte::class, 1);
    }

    public function test_failed_webhook_does_not_dispatch(): void
    {
        $this->runJob($this->webhook(false, 'FAILED'));

        Queue::assertNotPushed(PushPatientToOdbInsyte::class);
    }

    public function test_disabled_flag_does_not_dispatch(): void
    {
        config(['services.odb_insyte.enabled' => false]);

        $this->runJob($this->webhook());

        Queue::assertNotPushed(PushPatientToOdbInsyte::class);
    }
}

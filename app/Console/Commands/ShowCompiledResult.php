<?php

namespace App\Console\Commands;

use App\Models\TestResult;
use App\Services\MyHealthService;
use App\Services\TestResultCompilerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ShowCompiledResult extends Command
{
    protected $signature = 'testing:show-compiled-result
        {--lab_no= : The lab number of the test result to display}';

    protected $description = 'Display compiled test result data (including MyHealth) for a given lab number';

    public function handle(): int
    {
        $labNo = $this->option('lab_no');

        if (empty($labNo)) {
            $this->error('--lab_no is required.');

            return self::FAILURE;
        }

        Log::channel('ai-command')->info('ShowCompiledResult: Starting', ['lab_no' => $labNo]);

        $this->line("Looking up lab number: {$labNo}");
        $this->line('');

        $testResult = TestResult::with([
            'patient',
            'testResultItems.panelPanelItem.panel.panelCategory',
            'testResultItems.referenceRange',
            'testResultItems.panelPanelItem.panelItem',
            'testResultItems.panelComments.masterPanelComment',
            'testResultSpecialTests.panelPanelItem.panelItem',
            'testResultSpecialTests.panelInterpretation',
        ])->where('lab_no', $labNo)->first();

        if (! $testResult) {
            $this->error("No test result found for lab_no: {$labNo}");
            Log::channel('ai-command')->warning('ShowCompiledResult: Not found', ['lab_no' => $labNo]);

            return self::FAILURE;
        }

        $this->line('--- Test Result ---');
        $this->line('  id            : ' . $testResult->id);
        $this->line('  lab_no        : ' . ($testResult->lab_no ?? 'null'));
        $this->line('  ref_id        : ' . ($testResult->ref_id ?? 'null'));
        $this->line('  patient_id    : ' . ($testResult->patient_id ?? 'null'));
        $this->line('  is_completed  : ' . ($testResult->is_completed ? 'true' : 'false'));
        $this->line('  is_reviewed   : ' . ($testResult->is_reviewed ? 'true' : 'false'));
        $this->line('  collected_date: ' . ($testResult->collected_date?->format('Y-m-d H:i:s') ?? 'null'));
        $this->line('  reported_date : ' . ($testResult->reported_date?->format('Y-m-d H:i:s') ?? 'null'));
        $this->line('');

        $this->line('Recalculating special tests...');
        Log::channel('ai-command')->info('ShowCompiledResult: Recalculating special tests', [
            'lab_no' => $labNo,
            'test_result_id' => $testResult->id,
        ]);

        $recalcExitCode = $this->callSilently('special-tests:recalculate', ['ids' => (string) $testResult->id]);

        if ($recalcExitCode !== self::SUCCESS) {
            $this->warn('Special test recalculation encountered errors. Results may be incomplete.');
            Log::channel('ai-command')->warning('ShowCompiledResult: Special test recalculation reported failure', [
                'lab_no' => $labNo,
                'test_result_id' => $testResult->id,
            ]);
        } else {
            $this->line('Special tests recalculated.');
            Log::channel('ai-command')->info('ShowCompiledResult: Special tests recalculated', [
                'lab_no' => $labNo,
                'test_result_id' => $testResult->id,
            ]);
        }

        $testResult->load([
            'testResultSpecialTests.panelPanelItem.panelItem',
            'testResultSpecialTests.panelInterpretation',
        ]);

        $this->line('');

        try {
            $compiler = app(TestResultCompilerService::class);
            $compiled = $compiler->compileTestResultData($testResult);
        } catch (Throwable $e) {
            $this->error('Failed to compile test result data: ' . $e->getMessage());
            Log::channel('ai-command')->error('ShowCompiledResult: Compile failed', [
                'lab_no' => $labNo,
                'test_result_id' => $testResult->id,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        $this->line(json_encode($compiled, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->line('');
        $this->info("Compiled result displayed for lab_no: {$labNo} (test_result_id: {$testResult->id})");

        Log::channel('ai-command')->info('ShowCompiledResult: Completed', [
            'lab_no' => $labNo,
            'test_result_id' => $testResult->id,
        ]);

        return self::SUCCESS;
    }
}

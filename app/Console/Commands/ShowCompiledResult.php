<?php

namespace App\Console\Commands;

use App\Models\TestResult;
use App\Services\MyHealthService;
use App\Services\TestResultCompilerService;
use Exception;
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

        // ── Health History ────────────────────────────────────────────────────
        $this->line('--- Health History (MyHealth) ---');

        $healthHistory = $compiled['Health History'] ?? [];

        $this->line('  Age    : ' . ($healthHistory['Age'] ?? 'null'));
        $this->line('  Gender : ' . ($healthHistory['Gender'] ?? 'null'));

        foreach ($healthHistory as $key => $value) {
            if ($key === 'Age' || $key === 'Gender') {
                continue;
            }

            // Date-keyed vitals block
            $this->line('');
            $this->line("  Vitals on {$key}:");

            if (empty($value)) {
                $this->line('    (none)');
                continue;
            }

            $rows = [];
            foreach ($value as $paramName => $detail) {
                try {
                    $rows[] = [
                        $paramName,
                        $detail->result ?? 'null',
                        $detail->unit ?? '',
                        $detail->range ?? ($detail->min_range . ' - ' . $detail->max_range),
                    ];
                } catch (Exception $e) {
                    $rows[] = [$paramName, 'error reading value', '', ''];
                }
            }

            $this->table(['Parameter', 'Result', 'Unit', 'Range'], $rows);
        }

        if (count(array_diff_key($healthHistory, ['Age' => true, 'Gender' => true])) === 0) {
            $this->line('  (no MyHealth vitals found within the last 14 days)');
        }

        $this->line('');

        // ── Blood Test Results ────────────────────────────────────────────────
        $this->line('--- Blood Test Results ---');

        $bloodTestResults = $compiled['Blood Test Results'] ?? [];

        if (empty($bloodTestResults)) {
            $this->warn('  (no blood test result data compiled)');
        }

        foreach ($bloodTestResults as $reportDate => $panels) {
            $this->line('');
            $this->line("  Report Date: {$reportDate}");

            foreach ($panels as $panelName => $items) {
                $this->line('');
                $this->line("  Panel: {$panelName}");

                $rows = [];
                foreach ($items as $item) {
                    $comments = ! empty($item['comments']) ? implode('; ', $item['comments']) : '';
                    $rows[] = [
                        $item['panel_item_name'] ?? '',
                        $item['result_value'] ?? '',
                        $item['panel_item_unit'] ?? '',
                        $item['result_status'] ?? '',
                        $item['reference_range'] ?? '',
                        $comments,
                    ];
                }

                $this->table(
                    ['Test', 'Value', 'Unit', 'Status', 'Reference Range', 'Comments'],
                    $rows
                );
            }
        }

        $this->line('');
        $this->info("Compiled result displayed for lab_no: {$labNo} (test_result_id: {$testResult->id})");

        Log::channel('ai-command')->info('ShowCompiledResult: Completed', [
            'lab_no' => $labNo,
            'test_result_id' => $testResult->id,
        ]);

        return self::SUCCESS;
    }
}

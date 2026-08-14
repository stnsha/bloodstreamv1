<?php

namespace App\Console\Commands;

use App\Models\IncompleteTestResult;
use App\Models\TestResult;
use App\Models\TestResultItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class MarkLabNoCompletedFromExcel extends Command
{
    protected $signature = 'lab-no:mark-completed-from-excel
                            {file : Filename (looked up in storage/app/public/excel) or absolute path to the incomplete_test_results xlsx export}
                            {--dry-run : Preview affected lab numbers without making any changes}
                            {--force : Skip the confirmation prompt (required for unattended/scheduled runs)}';

    protected $description = 'Read an incomplete_test_results xlsx export and mark lab_no rows as completed based on the Remarks column (UM omitted, No result, No Order HCG, Already Sent*, No Lipid test and FBC, Result sent). Empty or unrecognized remarks are skipped.';

    /**
     * Remark values (lowercased, trimmed) that mark a lab_no completed
     * outright, no further checks needed.
     */
    private const AUTO_COMPLETE_REMARKS = [
        'um omitted',
        'no result',
        'no order hcg',
        'no lipid test and fbc',
        'result sent',
    ];

    public function handle(): int
    {
        $fileArg = $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $path = $this->resolvePath($fileArg);

        Log::channel('ai-command')->info('MarkLabNoCompletedFromExcel: started', [
            'file_arg' => $fileArg,
            'resolved_path' => $path,
            'dry_run' => $dryRun,
        ]);

        if (! is_file($path)) {
            $this->error("File not found: {$path}");
            $this->line('Note: on production this file is expected under C:\\xampp\\htdocs\\production\\storage\\app\\public\\excel — on local under C:\\laragon\\www\\blood-stream-v1\\storage\\app\\public\\excel. Pass an absolute path to override.');

            Log::channel('ai-command')->error('MarkLabNoCompletedFromExcel: file not found', ['path' => $path]);

            return self::FAILURE;
        }

        try {
            $rows = $this->readRows($path);
        } catch (Throwable $e) {
            $this->error("Failed to read xlsx: {$e->getMessage()}");

            Log::channel('ai-command')->error('MarkLabNoCompletedFromExcel: failed to read xlsx', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        if (empty($rows)) {
            $this->info('No data rows found in file.');

            return self::SUCCESS;
        }

        [$labNoIdx, $remarksIdx] = $this->resolveColumns($rows[0]);

        if ($labNoIdx === null || $remarksIdx === null) {
            $this->error('Could not find both "lab_no" and "Remarks" columns in the header row.');

            Log::channel('ai-command')->error('MarkLabNoCompletedFromExcel: missing required columns', [
                'header' => $rows[0],
            ]);

            return self::FAILURE;
        }

        $dataRows = array_slice($rows, 1);

        // Evaluate every row up front (read-only DB checks included) so the
        // dry-run preview shows the exact same outcome the live run would
        // produce — same lab_no lookup, same "does it have panels" check.
        $plan = $this->evaluatePlan($dataRows, $labNoIdx, $remarksIdx);

        $counts = $this->summarizeOutcomes($plan);

        $this->info("Total rows with lab_no: {$counts['total']}.");
        $this->line('');
        $this->previewTable($plan);
        $this->line('');
        $this->printSummary($counts);

        if ($dryRun) {
            $this->line('');
            $this->info('DRY RUN — no changes made.');

            Log::channel('ai-command')->info('MarkLabNoCompletedFromExcel: dry run completed', $counts);

            return self::SUCCESS;
        }

        $this->line('');

        if (! $force && ! $this->confirm("Mark {$counts['mark']} lab_no(s) as completed?")) {
            $this->info('Operation cancelled.');
            Log::channel('ai-command')->info('MarkLabNoCompletedFromExcel: cancelled by user');

            return self::SUCCESS;
        }

        $marked = 0;
        $failed = 0;
        $resultRows = [];

        foreach ($plan as $item) {
            if ($item['outcome'] !== 'mark') {
                continue;
            }

            $labNo = $item['lab_no'];

            try {
                $testResult = TestResult::find($item['test_result_id']);

                if (! $testResult) {
                    $failed++;
                    $resultRows[] = [$labNo, 'FAILED', 'Test result vanished between evaluation and write'];
                    continue;
                }

                $testResult->is_completed = true;
                $testResult->manually_completed_at = now();
                $testResult->save();

                IncompleteTestResult::where('test_result_id', $testResult->id)->delete();

                $marked++;
                $resultRows[] = [$labNo, 'MARKED COMPLETED', 'report_id='.$testResult->id];

                Log::channel('ai-command')->info('MarkLabNoCompletedFromExcel: marked completed', [
                    'lab_no' => $labNo,
                    'test_result_id' => $testResult->id,
                    'remark' => $item['remark'],
                ]);
            } catch (Throwable $e) {
                $failed++;
                $resultRows[] = [$labNo, 'FAILED', $e->getMessage()];

                Log::channel('ai-command')->error('MarkLabNoCompletedFromExcel: failed to process row', [
                    'lab_no' => $labNo,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->line('');
        $this->table(['Lab No', 'Status', 'Detail'], $resultRows);
        $this->line('');
        $this->info("Done. Marked: {$marked}, Already completed: {$counts['already_completed']}, Skipped (empty remark): {$counts['skip_empty']}, Skipped (unrecognized remark): {$counts['skip_unrecognized']}, Skipped (no panels): {$counts['skip_no_panels']}, Skipped (not found): {$counts['skip_not_found']}, Failed: {$failed}.");

        Log::channel('ai-command')->info('MarkLabNoCompletedFromExcel: completed', [
            'marked' => $marked,
            'already_completed' => $counts['already_completed'],
            'skipped_empty' => $counts['skip_empty'],
            'skipped_unrecognized' => $counts['skip_unrecognized'],
            'skipped_no_panels' => $counts['skip_no_panels'],
            'skipped_not_found' => $counts['skip_not_found'],
            'failed' => $failed,
        ]);

        return $failed > 0 && $marked === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Classify every lab_no row and resolve its final outcome, including the
     * read-only DB checks (test result lookup, panel-existence check for
     * "Already Sent"-family remarks, already-completed check) — shared by
     * the dry-run preview and the live run so both report identical numbers.
     *
     * Outcome values: 'mark' (will be/would be marked completed),
     * 'already_completed', 'skip_no_panels' (Already Sent family with no
     * test_result_items), 'skip_not_found', 'skip_empty',
     * 'skip_unrecognized'.
     *
     * @return array<int, array{lab_no: string, remark: string, action: string, outcome: string, test_result_id: int|null}>
     */
    private function evaluatePlan(array $dataRows, int $labNoIdx, int $remarksIdx): array
    {
        $plan = [];

        foreach ($dataRows as $row) {
            $labNo = trim((string) ($row[$labNoIdx] ?? ''));
            $remark = trim((string) ($row[$remarksIdx] ?? ''));

            if ($labNo === '') {
                continue;
            }

            $action = $this->classifyRemark($remark);
            $testResultId = null;

            if ($action === 'skip_empty' || $action === 'skip_unrecognized') {
                $outcome = $action;
            } else {
                $testResult = TestResult::where('lab_no', $labNo)->latest()->first();

                if (! $testResult) {
                    $outcome = 'skip_not_found';
                } elseif ($action === 'mark_if_panels_exist' && ! TestResultItem::where('test_result_id', $testResult->id)->exists()) {
                    $outcome = 'skip_no_panels';
                } elseif ($testResult->is_completed && $testResult->manually_completed_at) {
                    $outcome = 'already_completed';
                } else {
                    $outcome = 'mark';
                    $testResultId = $testResult->id;
                }
            }

            $plan[] = [
                'lab_no' => $labNo,
                'remark' => $remark,
                'action' => $action,
                'outcome' => $outcome,
                'test_result_id' => $testResultId,
            ];
        }

        return $plan;
    }

    /**
     * @return array{total: int, mark: int, already_completed: int, skip_empty: int, skip_unrecognized: int, skip_no_panels: int, skip_not_found: int}
     */
    private function summarizeOutcomes(array $plan): array
    {
        $counts = [
            'total' => count($plan),
            'mark' => 0,
            'already_completed' => 0,
            'skip_empty' => 0,
            'skip_unrecognized' => 0,
            'skip_no_panels' => 0,
            'skip_not_found' => 0,
        ];

        foreach ($plan as $item) {
            $counts[$item['outcome']]++;
        }

        return $counts;
    }

    private function printSummary(array $counts): void
    {
        $this->info("Will be marked completed: {$counts['mark']}");
        $this->info("Already completed (no change needed): {$counts['already_completed']}");
        $this->info("Skipped — no panels found (Already Sent* check failed): {$counts['skip_no_panels']}");
        $this->info("Skipped — test result not found: {$counts['skip_not_found']}");
        $this->info("Skipped — empty remark: {$counts['skip_empty']}");
        $this->info("Skipped — unrecognized remark: {$counts['skip_unrecognized']}");
    }

    /**
     * Resolve the file argument to an absolute path. An argument containing
     * a path separator or a drive letter is used as-is (so an explicit
     * production path can be passed); otherwise it's looked up under this
     * environment's storage/app/public/excel directory, which resolves
     * correctly for both C:\laragon\www\blood-stream-v1 (local) and
     * C:\xampp\htdocs\production (production) since storage_path() is
     * derived from the app's own base_path().
     */
    private function resolvePath(string $file): string
    {
        if (str_contains($file, '/') || str_contains($file, '\\')) {
            return $file;
        }

        return storage_path('app/public/excel/'.$file);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readRows(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }

    /**
     * @return array{0: int|null, 1: int|null} [lab_no column index, Remarks column index]
     */
    private function resolveColumns(array $headerRow): array
    {
        $labNoIdx = null;
        $remarksIdx = null;

        foreach ($headerRow as $idx => $header) {
            $normalized = strtolower(trim((string) $header));

            if ($normalized === 'lab_no') {
                $labNoIdx = $idx;
            }

            if ($normalized === 'remarks') {
                $remarksIdx = $idx;
            }
        }

        return [$labNoIdx, $remarksIdx];
    }

    private function classifyRemark(string $remark): string
    {
        if ($remark === '') {
            return 'skip_empty';
        }

        $normalized = $this->normalizeRemark($remark);

        if (in_array($normalized, self::AUTO_COMPLETE_REMARKS, true)) {
            return 'mark';
        }

        if (str_starts_with($normalized, 'already sent')) {
            // Prefix match: covers "Already Sent", "Already Sent.", and any
            // "Already Sent. <detail>" variant (magnesium/electrolytes not
            // ordered, albumin/AST/ALT already sent, etc).
            return 'mark_if_panels_exist';
        }

        if ($this->looksLikeAlreadySentTypo($normalized)) {
            return 'mark_if_panels_exist';
        }

        return 'skip_unrecognized';
    }

    /**
     * Catch data-entry typos of "Already Sent" (e.g. "Alreadt Sent") that
     * the exact prefix match misses: first word within edit-distance 2 of
     * "already", second word exactly "sent".
     */
    private function looksLikeAlreadySentTypo(string $normalized): bool
    {
        $words = preg_split('/\s+/', $normalized, 3) ?: [];
        $first = $words[0] ?? '';
        $second = $words[1] ?? '';

        return $second === 'sent' && levenshtein($first, 'already') <= 2;
    }

    /**
     * Lowercase, trim, and strip trailing punctuation/whitespace so minor
     * formatting variants of the same remark (e.g. "Result sent." vs
     * "Result sent") compare equal.
     */
    private function normalizeRemark(string $remark): string
    {
        $normalized = strtolower(trim($remark));

        return rtrim($normalized, ". \t\n\r\0\x0B");
    }

    private function previewTable(array $plan): void
    {
        $labels = [
            'mark' => 'WILL MARK COMPLETED',
            'already_completed' => 'ALREADY COMPLETED',
            'skip_no_panels' => 'SKIP — no panels found',
            'skip_not_found' => 'SKIP — test result not found',
            'skip_empty' => 'SKIP — empty remark',
            'skip_unrecognized' => 'SKIP — unrecognized remark',
        ];

        $rows = array_map(
            fn ($p) => [$p['lab_no'], $p['remark'] ?: '(empty)', $labels[$p['outcome']] ?? $p['outcome']],
            $plan
        );

        $this->table(['Lab No', 'Remark', 'Outcome'], $rows);
    }
}

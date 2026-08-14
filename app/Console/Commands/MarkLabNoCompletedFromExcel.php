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

        // Classify every row up front so the dry-run preview and the live
        // run share exactly the same decision logic.
        $plan = [];

        foreach ($dataRows as $row) {
            $labNo = trim((string) ($row[$labNoIdx] ?? ''));
            $remark = trim((string) ($row[$remarksIdx] ?? ''));

            if ($labNo === '') {
                continue;
            }

            $plan[] = [
                'lab_no' => $labNo,
                'remark' => $remark,
                'action' => $this->classifyRemark($remark),
            ];
        }

        $toMarkCount = count(array_filter($plan, fn ($p) => in_array($p['action'], ['mark', 'mark_if_panels_exist'], true)));

        $this->info('Total rows with lab_no: '.count($plan).'. Candidates to mark completed: '.$toMarkCount.'.');
        $this->line('');

        if ($dryRun) {
            $this->previewTable($plan, dryRun: true);
            $this->line('');
            $this->info('DRY RUN — no changes made.');

            Log::channel('ai-command')->info('MarkLabNoCompletedFromExcel: dry run completed', [
                'total_rows' => count($plan),
                'candidates' => $toMarkCount,
            ]);

            return self::SUCCESS;
        }

        $this->previewTable($plan, dryRun: false);
        $this->line('');

        if (! $force && ! $this->confirm("Mark {$toMarkCount} lab_no(s) as completed?")) {
            $this->info('Operation cancelled.');
            Log::channel('ai-command')->info('MarkLabNoCompletedFromExcel: cancelled by user');

            return self::SUCCESS;
        }

        $marked = 0;
        $alreadyCompleted = 0;
        $skippedEmpty = 0;
        $skippedUnrecognized = 0;
        $skippedNoPanels = 0;
        $skippedNotFound = 0;
        $failed = 0;
        $resultRows = [];

        foreach ($plan as $item) {
            $labNo = $item['lab_no'];
            $action = $item['action'];

            if ($action === 'skip_empty') {
                $skippedEmpty++;
                continue;
            }

            if ($action === 'skip_unrecognized') {
                $skippedUnrecognized++;
                $resultRows[] = [$labNo, 'SKIPPED', 'Unrecognized remark: '.$item['remark']];
                continue;
            }

            try {
                $testResult = TestResult::where('lab_no', $labNo)->latest()->first();

                if (! $testResult) {
                    $skippedNotFound++;
                    $resultRows[] = [$labNo, 'SKIPPED', 'Test result not found'];
                    continue;
                }

                if ($action === 'mark_if_panels_exist') {
                    $hasPanels = TestResultItem::where('test_result_id', $testResult->id)->exists();

                    if (! $hasPanels) {
                        $skippedNoPanels++;
                        $resultRows[] = [$labNo, 'SKIPPED', 'No panels found for "Already Sent" remark'];

                        Log::channel('ai-command')->info('MarkLabNoCompletedFromExcel: skipped, no panels for Already Sent remark', [
                            'lab_no' => $labNo,
                            'test_result_id' => $testResult->id,
                        ]);

                        continue;
                    }
                }

                if ($testResult->is_completed && $testResult->manually_completed_at) {
                    $alreadyCompleted++;
                    $resultRows[] = [$labNo, 'ALREADY COMPLETED', '-'];
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
        $this->info("Done. Marked: {$marked}, Already completed: {$alreadyCompleted}, Skipped (empty remark): {$skippedEmpty}, Skipped (unrecognized remark): {$skippedUnrecognized}, Skipped (no panels): {$skippedNoPanels}, Skipped (not found): {$skippedNotFound}, Failed: {$failed}.");

        Log::channel('ai-command')->info('MarkLabNoCompletedFromExcel: completed', [
            'marked' => $marked,
            'already_completed' => $alreadyCompleted,
            'skipped_empty' => $skippedEmpty,
            'skipped_unrecognized' => $skippedUnrecognized,
            'skipped_no_panels' => $skippedNoPanels,
            'skipped_not_found' => $skippedNotFound,
            'failed' => $failed,
        ]);

        return $failed > 0 && $marked === 0 ? self::FAILURE : self::SUCCESS;
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

    private function previewTable(array $plan, bool $dryRun): void
    {
        $labels = [
            'mark' => 'MARK COMPLETED',
            'mark_if_panels_exist' => 'MARK IF PANELS EXIST',
            'skip_empty' => 'SKIP (empty remark)',
            'skip_unrecognized' => 'SKIP (unrecognized remark)',
        ];

        $rows = array_map(
            fn ($p) => [$p['lab_no'], $p['remark'] ?: '(empty)', $labels[$p['action']] ?? $p['action']],
            $plan
        );

        $this->table(['Lab No', 'Remark', ($dryRun ? 'Planned Action' : 'Action')], $rows);
    }
}

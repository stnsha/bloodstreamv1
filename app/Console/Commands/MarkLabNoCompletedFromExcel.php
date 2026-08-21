<?php

namespace App\Console\Commands;

use App\Models\IncompleteTestResult;
use App\Models\TestResult;
use App\Models\TestResultItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class MarkLabNoCompletedFromExcel extends Command
{
    protected $signature = 'lab-no:mark-completed-from-excel
                            {file : Filename (looked up in storage/app/public/excel) or absolute path to the incomplete_test_results xlsx export}
                            {--dry-run : Preview affected lab numbers without making any changes}
                            {--force : Skip the confirmation prompt (required for unattended/scheduled runs)}';

    protected $description = 'Read an incomplete_test_results xlsx export (sheet incomplete_test_results_YYYY-MM) and mark lab_no rows as completed based on the Remarks column: "result sent" (verified against missing_details panels actually having results) or "sample/edta clotted" (sample ruined, no result ever coming) -> mark completed; "result pending"/"result processing" -> still incomplete, skipped; empty or unrecognized remarks are skipped.';

    /**
     * Remark substrings meaning the sample was ruined, so no result will
     * ever come for it -- mark completed unconditionally, no panel check.
     */
    private const RUINED_SAMPLE_REMARK_SUBSTRINGS = [
        'sample clotted',
        'edta clotted',
    ];

    /**
     * Remark substrings meaning the lab_no is still genuinely incomplete
     * (result not ready yet) -- must NOT be marked completed.
     */
    private const STILL_INCOMPLETE_REMARK_SUBSTRINGS = [
        'result pending',
        'result processing',
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

        [$labNoIdx, $remarksIdx, $missingDetailsIdx] = $this->resolveColumns($rows[0]);

        if ($labNoIdx === null || $remarksIdx === null || $missingDetailsIdx === null) {
            $this->error('Could not find "lab_no", "Remarks", and "missing_details" columns in the header row.');

            Log::channel('ai-command')->error('MarkLabNoCompletedFromExcel: missing required columns', [
                'header' => $rows[0],
            ]);

            return self::FAILURE;
        }

        $dataRows = array_slice($rows, 1);

        // Evaluate every row up front (read-only DB checks included) so the
        // dry-run preview shows the exact same outcome the live run would
        // produce — same lab_no lookup, same "does it have panels" check.
        $plan = $this->evaluatePlan($dataRows, $labNoIdx, $remarksIdx, $missingDetailsIdx);

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
        $this->info("Done. Marked: {$marked}, Already completed: {$counts['already_completed']}, Skipped (empty remark): {$counts['skip_empty']}, Skipped (unrecognized remark): {$counts['skip_unrecognized']}, Skipped (still incomplete): {$counts['skip_incomplete']}, Skipped (missing panels not yet resulted): {$counts['skip_no_panels']}, Skipped (not found): {$counts['skip_not_found']}, Failed: {$failed}.");

        Log::channel('ai-command')->info('MarkLabNoCompletedFromExcel: completed', [
            'marked' => $marked,
            'already_completed' => $counts['already_completed'],
            'skipped_empty' => $counts['skip_empty'],
            'skipped_unrecognized' => $counts['skip_unrecognized'],
            'skipped_incomplete' => $counts['skip_incomplete'],
            'skipped_no_panels' => $counts['skip_no_panels'],
            'skipped_not_found' => $counts['skip_not_found'],
            'failed' => $failed,
        ]);

        return $failed > 0 && $marked === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Classify every lab_no row and resolve its final outcome, including the
     * read-only DB checks (test result lookup, missing-panels-now-have-
     * results verification for "result sent" rows, already-completed check)
     * — shared by the dry-run preview and the live run so both report
     * identical numbers.
     *
     * Outcome values: 'mark' (will be/would be marked completed),
     * 'already_completed', 'skip_incomplete' (result genuinely still
     * pending/processing), 'skip_no_panels' (remark claims "result sent"
     * but missing_details panels still have no recorded result),
     * 'skip_not_found', 'skip_empty', 'skip_unrecognized'.
     *
     * @return array<int, array{lab_no: string, remark: string, action: string, outcome: string, test_result_id: int|null}>
     */
    private function evaluatePlan(array $dataRows, int $labNoIdx, int $remarksIdx, int $missingDetailsIdx): array
    {
        $plan = [];

        foreach ($dataRows as $row) {
            $labNo = trim((string) ($row[$labNoIdx] ?? ''));
            $remark = trim((string) ($row[$remarksIdx] ?? ''));
            $missingDetails = trim((string) ($row[$missingDetailsIdx] ?? ''));

            if ($labNo === '') {
                continue;
            }

            $action = $this->classifyRemark($remark);
            $testResultId = null;

            if ($action === 'skip_empty' || $action === 'skip_unrecognized' || $action === 'skip_incomplete') {
                $outcome = $action;
            } else {
                $testResult = TestResult::where('lab_no', $labNo)->latest()->first();

                if (! $testResult) {
                    $outcome = 'skip_not_found';
                } elseif (
                    $action === 'mark_result_sent'
                    && ! $this->missingPanelsNowHaveResults($testResult->id, $this->parseMissingPanelNames($missingDetails))
                ) {
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
     * @return array{total: int, mark: int, already_completed: int, skip_empty: int, skip_unrecognized: int, skip_incomplete: int, skip_no_panels: int, skip_not_found: int}
     */
    private function summarizeOutcomes(array $plan): array
    {
        $counts = [
            'total' => count($plan),
            'mark' => 0,
            'already_completed' => 0,
            'skip_empty' => 0,
            'skip_unrecognized' => 0,
            'skip_incomplete' => 0,
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
        $this->info("Skipped — still incomplete (result pending/processing): {$counts['skip_incomplete']}");
        $this->info("Skipped — 'result sent' claimed but missing panels still have no result: {$counts['skip_no_panels']}");
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
        $sheet = $this->resolveSheet($spreadsheet);
        $rows = $sheet->toArray(null, true, true, false);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }

    /**
     * Prefer the "incomplete_test_results_..." sheet (the export's data
     * sheet, alongside other reference sheets such as "AMCESN PROFILE");
     * fall back to the active sheet if no such sheet is found.
     */
    private function resolveSheet(Spreadsheet $spreadsheet): Worksheet
    {
        foreach ($spreadsheet->getSheetNames() as $name) {
            if (str_starts_with(strtolower($name), 'incomplete_test_results')) {
                return $spreadsheet->getSheetByName($name);
            }
        }

        return $spreadsheet->getActiveSheet();
    }

    /**
     * @return array{0: int|null, 1: int|null, 2: int|null} [lab_no column index, Remarks column index, missing_details column index]
     */
    private function resolveColumns(array $headerRow): array
    {
        $labNoIdx = null;
        $remarksIdx = null;
        $missingDetailsIdx = null;

        foreach ($headerRow as $idx => $header) {
            $normalized = strtolower(trim((string) $header));

            if ($normalized === 'lab_no') {
                $labNoIdx = $idx;
            }

            if ($normalized === 'remarks') {
                $remarksIdx = $idx;
            }

            if ($normalized === 'missing_details') {
                $missingDetailsIdx = $idx;
            }
        }

        return [$labNoIdx, $remarksIdx, $missingDetailsIdx];
    }

    /**
     * Remarks are free-text (often multi-line, with test codes and
     * timestamps mixed in, e.g. "AFP, CEA, FE - result sent on Fri, Aug 14,
     * 2026\nTo check AMCESN package"), so classification is substring-based
     * rather than exact match.
     */
    private function classifyRemark(string $remark): string
    {
        if ($remark === '') {
            return 'skip_empty';
        }

        $normalized = strtolower($remark);

        foreach (self::STILL_INCOMPLETE_REMARK_SUBSTRINGS as $needle) {
            if (str_contains($normalized, $needle)) {
                return 'skip_incomplete';
            }
        }

        foreach (self::RUINED_SAMPLE_REMARK_SUBSTRINGS as $needle) {
            if (str_contains($normalized, $needle)) {
                return 'mark_ruined';
            }
        }

        if (str_contains($normalized, 'result sent')) {
            return 'mark_result_sent';
        }

        return 'skip_unrecognized';
    }

    /**
     * Parses "Missing panels: A, B, C" out of the missing_details column
     * into a plain list of panel names ["A", "B", "C"]. Returns [] when the
     * column doesn't follow that format (nothing to verify against).
     */
    private function parseMissingPanelNames(string $missingDetails): array
    {
        $missingDetails = trim($missingDetails);

        if ($missingDetails === '') {
            return [];
        }

        $missingDetails = preg_replace('/^missing panels:\s*/i', '', $missingDetails);

        $names = array_map('trim', explode(',', $missingDetails));

        return array_values(array_filter($names, fn ($name) => $name !== ''));
    }

    /**
     * Verifies that every panel named in $panelNames now has at least one
     * non-null TestResultItem value recorded against this test result --
     * i.e. the "result sent" remark is actually backed by data, not just a
     * hopeful note. Panel name matching is case-insensitive against
     * panels.name via the panel_panel_items pivot.
     */
    private function missingPanelsNowHaveResults(int $testResultId, array $panelNames): bool
    {
        if (empty($panelNames)) {
            // No parseable panel list to verify against -- don't block on it.
            return true;
        }

        $normalizedNames = array_unique(array_map('strtoupper', $panelNames));

        $matchedNames = TestResultItem::query()
            ->join('panel_panel_items', 'panel_panel_items.id', '=', 'test_result_items.panel_panel_item_id')
            ->join('panels', 'panels.id', '=', 'panel_panel_items.panel_id')
            ->where('test_result_items.test_result_id', $testResultId)
            ->whereNotNull('test_result_items.value')
            ->whereIn(DB::raw('UPPER(panels.name)'), $normalizedNames)
            ->distinct()
            ->pluck(DB::raw('UPPER(panels.name) as panel_name'))
            ->all();

        return count(array_unique($matchedNames)) >= count($normalizedNames);
    }

    private function previewTable(array $plan): void
    {
        $labels = [
            'mark' => 'WILL MARK COMPLETED',
            'already_completed' => 'ALREADY COMPLETED',
            'skip_incomplete' => 'SKIP — still incomplete (pending/processing)',
            'skip_no_panels' => 'SKIP — missing panels still have no result',
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

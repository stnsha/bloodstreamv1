<?php

namespace App\Console\Commands;

use App\Constants\ConsultCall\PanelPanelItem;
use App\Models\ClinicalCondition;
use App\Models\Patient;
use App\Models\TestResult;
use App\Services\ConditionEvaluatorService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckAnemiaEligibility extends Command
{
    protected $signature = 'testing:check-anemia-eligibility
        {--test_result_id= : Check a single test result by ID (overrides all date/limit/offset options)}
        {--date-from=2026-06-02 : Start date filter (collected_date, inclusive)}
        {--date-to= : End date filter (collected_date, inclusive), defaults to today}
        {--limit=50 : Number of test results to display}
        {--offset=0 : Skip first N records}';

    protected $description = 'Display panel values and clinical condition interpretation for completed test results (diagnostic only, no database writes)';

    private const PANEL_LABELS = [
        'hae'     => 'Haemoglobin (Hb)',
        'rcc'     => 'Red Cell Count (RCC)',
        'pcv'     => 'Packed Cell Volume (PCV/HCT)',
        'mcv'     => 'Mean Cell Volume (MCV)',
        'mch'     => 'Mean Cell Haemoglobin (MCH)',
        'mchc'    => 'Mean Corpuscular Haemoglobin Concentration (MCHC)',
        'rdw'     => 'Red Cell Distribution Width (RDW)',
        's_iron'  => 'Serum Iron',
        'ferritin' => 'Ferritin',
    ];

    public function handle(): int
    {
        $testResultId = $this->option('test_result_id');

        if ($testResultId !== null) {
            if (! is_numeric($testResultId)) {
                $this->error('--test_result_id must be a numeric value.');

                return self::FAILURE;
            }

            $testResult = TestResult::find((int) $testResultId);

            if (! $testResult) {
                $this->error("Test result ID {$testResultId} not found.");

                return self::FAILURE;
            }

            Log::info('CheckAnemiaEligibility: Starting (single)', ['test_result_id' => $testResultId]);

            $testResults = collect([$testResult]);
        } else {
            $dateFrom = $this->option('date-from');
            $dateTo   = $this->option('date-to') ?: Carbon::today()->toDateString();
            $limit    = (int) $this->option('limit');
            $offset   = (int) $this->option('offset');

            Log::info('CheckAnemiaEligibility: Starting', [
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
                'limit'     => $limit,
                'offset'    => $offset,
            ]);

            $this->info("Querying completed test results (date_from={$dateFrom}, date_to={$dateTo}, limit={$limit}, offset={$offset})");

            $testResults = TestResult::where('is_completed', true)
                ->whereNotNull('patient_id')
                ->whereNotNull('ref_id')
                ->whereBetween('collected_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->orderBy('collected_date', 'desc')
                ->orderBy('id', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            if ($testResults->count() === 0) {
                $this->warn('No test results found matching criteria.');
                Log::info('CheckAnemiaEligibility: No records found', [
                    'date_from' => $dateFrom,
                    'date_to'   => $dateTo,
                ]);

                return self::SUCCESS;
            }

            $this->info("Found {$testResults->count()} test results. Displaying only those with an anemia condition match (ID >= 31).");
        }

        $total = $testResults->count();

        $evaluator = app(ConditionEvaluatorService::class);

        // Bypass active_from so anemia conditions can be tested before June 2nd activation
        $anemiaConditionIds = ClinicalCondition::active()
            ->where('criteria_count', '>', 0)
            ->where('id', '>=', 31)
            ->orderByDesc('risk_tier')
            ->orderByDesc('criteria_count')
            ->orderBy('id')
            ->pluck('id')
            ->toArray();

        $counters = [
            'scanned' => 0,
            'anemia'  => 0,
        ];
        $conditionFrequency = [];

        foreach ($testResults as $testResult) {
            try {
                $this->displayResult($testResult, $evaluator, $anemiaConditionIds, $counters, $conditionFrequency);
            } catch (Throwable $e) {
                $this->newLine();
                $this->error("Error processing test result #{$testResult->id}: " . $e->getMessage());
                Log::error('CheckAnemiaEligibility: Error processing test result', [
                    'test_result_id' => $testResult->id,
                    'error'          => $e->getMessage(),
                    'file'           => $e->getFile(),
                    'line'           => $e->getLine(),
                ]);
            }
        }

        $this->printSummary($counters, $conditionFrequency);

        Log::info('CheckAnemiaEligibility: Completed', [
            'total'    => $total,
            'counters' => $counters,
        ]);

        return self::SUCCESS;
    }

    private function displayResult(
        TestResult $testResult,
        ConditionEvaluatorService $evaluator,
        array $anemiaConditionIds,
        array &$counters,
        array &$conditionFrequency
    ): void {
        $patient = Patient::find($testResult->patient_id);

        if (! $patient) {
            return;
        }

        $items = $testResult->testResultItems()
            ->whereIn('panel_panel_item_id', PanelPanelItem::ALL_IDS)
            ->with('panelItem')
            ->get();

        $referenceDate = $testResult->collected_date ?? $testResult->reported_date;
        $age           = $this->resolvePatientAge($patient, $referenceDate ? Carbon::parse($referenceDate) : null);

        $panelValues = [];
        $panelUnits  = [];

        foreach (PanelPanelItem::REQUIRED_CATEGORIES as $category => $ids) {
            $value = null;
            $unit  = null;

            foreach ($ids as $panelItemId) {
                $item = $items->firstWhere('panel_panel_item_id', $panelItemId);
                if ($item && $item->value !== null && $item->value !== '') {
                    $value = (float) $item->value;
                    $unit  = $item->panelItem->unit ?? null;
                    break;
                }
            }

            $panelValues[$category] = $value;
            $panelUnits[$category]  = $unit;
        }

        $patientData = [
            'tc'            => $panelValues['tc'],
            'ldlc'          => $panelValues['ldlc'],
            'egfr'          => $panelValues['egfr'],
            'hba1c_percent' => $panelValues['hba1c_percent'],
            'alt'           => $panelValues['alt'],
            'age'           => $age,
            'bmi'           => null,
            'gender'        => $patient->gender,
            'hae'           => $panelValues['hae'],
            'rcc'           => $panelValues['rcc'],
            'pcv'           => $panelValues['pcv'],
            'mcv'           => $panelValues['mcv'],
            'mch'           => $panelValues['mch'],
            'mchc'          => $panelValues['mchc'],
            'rdw'           => $panelValues['rdw'],
            's_iron'        => $panelValues['s_iron'],
            'ferritin'      => $panelValues['ferritin'],
        ];

        $counters['scanned']++;

        $conditionId = null;
        foreach ($anemiaConditionIds as $id) {
            if ($evaluator->evaluateCondition($id, $patientData)) {
                $conditionId = $id;
                break;
            }
        }

        // Skip results with no anemia condition match
        if ($conditionId === null) {
            return;
        }

        $counters['anemia']++;
        $conditionFrequency[$conditionId] = ($conditionFrequency[$conditionId] ?? 0) + 1;

        // Header
        $collectedDate = $testResult->collected_date
            ? Carbon::parse($testResult->collected_date)->toDateString()
            : 'null';
        $gender = $patient->gender ?? 'null';

        $this->newLine();
        $this->line(str_repeat('=', 80));
        $this->line(
            "Test Result #{$testResult->id}" .
            " | Patient: {$testResult->patient_id}" .
            " | Gender: {$gender}" .
            " | Lab No: " . ($testResult->lab_no ?? 'null') .
            " | Ref ID: " . ($testResult->ref_id ?? 'null') .
            " | Collected: {$collectedDate}"
        );
        $this->line(str_repeat('=', 80));

        // Panel values table
        $this->newLine();
        $this->line('Panel Values:');

        $panelRows = [];
        foreach (self::PANEL_LABELS as $category => $label) {
            $value = $panelValues[$category] ?? null;
            $unit  = $panelUnits[$category] ?? '';

            $panelRows[] = [
                $label,
                $value !== null ? $value : '—',
                $unit ?? '',
            ];
        }

        $this->table(['Parameter', 'Value', 'Unit'], $panelRows);

        // Clinical condition interpretation table
        $condition   = ClinicalCondition::getCondition($conditionId);
        $description = $condition['description'] ?? "Condition {$conditionId}";
        $riskTier    = $condition['risk_tier'] ?? '—';

        $this->line('Clinical Condition Interpretation:');
        $this->table(
            ['Field', 'Value'],
            [
                ['Matched Condition', "[{$conditionId}] {$description}"],
                ['Risk Tier',         $riskTier],
                ['Active From',       PanelPanelItem::ANEMIA_ACTIVE_DATE],
            ]
        );
    }

    private function printSummary(array $counters, array $conditionFrequency): void
    {
        $this->newLine();
        $this->line(str_repeat('-', 80));
        $this->info('Summary');

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total scanned',          $counters['scanned']],
                ['Anemia match displayed', $counters['anemia']],
            ]
        );

        if (! empty($conditionFrequency)) {
            arsort($conditionFrequency);
            $frequencyRows = [];

            foreach ($conditionFrequency as $conditionId => $count) {
                $condition   = ClinicalCondition::getCondition($conditionId);
                $description = $condition['description'] ?? "Condition {$conditionId}";
                $frequencyRows[] = [$conditionId, $description, $count];
            }

            $this->info('Condition Frequency:');
            $this->table(['Condition ID', 'Description', 'Count'], $frequencyRows);
        }
    }

    private function resolvePatientAge(Patient $patient, ?Carbon $collectedDate): ?int
    {
        if ($patient->age !== null) {
            return (int) $patient->age;
        }

        if ($patient->dob && $collectedDate) {
            return calculatePatientAge($patient->dob, $collectedDate->toDateString());
        }

        if ($patient->dob) {
            return Carbon::parse($patient->dob)->age;
        }

        return null;
    }
}

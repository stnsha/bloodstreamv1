<?php

namespace App\Http\Controllers\API\ConsultCall;

use App\Constants\ConsultCall\PanelPanelItem;
use App\Http\Controllers\Controller;
use App\Models\TestResult;
use App\Services\ConditionEvaluatorService;
use App\Services\MyHealthService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

class ReportController extends Controller
{
    protected MyHealthService $myHealthService;

    protected ConditionEvaluatorService $conditionEvaluator;

    public function __construct(MyHealthService $myHealthService, ConditionEvaluatorService $conditionEvaluator)
    {
        $this->myHealthService = $myHealthService;
        $this->conditionEvaluator = $conditionEvaluator;
    }

    /**
     * Generate blood test result summary based on flexible date filtering.
     *
     * Supports three filtering modes:
     * 1. Year only - returns full year (Jan-Dec)
     * 2. Year + quarter - returns specified quarter
     * 3. Year + from_month + to_month - returns custom month range
     *
     * Only returns results that have ALL required panel categories:
     * TC, LDL-C, eGFR, HbA1c%, HbA1c, ALT
     *
     * Also evaluates 25 clinical conditions and returns statistics.
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:1900|max:2100',
            'quarter' => 'nullable|integer|in:1,2,3,4|prohibits:from_month,to_month',
            'from_month' => 'nullable|integer|min:1|max:12|required_with:to_month|prohibits:quarter',
            'to_month' => 'nullable|integer|min:1|max:12|required_with:from_month|gte:from_month',
        ]);

        $year = $validated['year'];
        $quarter = $validated['quarter'] ?? null;
        $fromMonth = $validated['from_month'] ?? null;
        $toMonth = $validated['to_month'] ?? null;

        $quarterMonths = [
            1 => [1, 3],   // Jan-Mar
            2 => [4, 6],   // Apr-Jun
            3 => [7, 9],   // Jul-Sep
            4 => [10, 12], // Oct-Dec
        ];

        // Determine start and end months based on filtering mode
        if ($quarter) {
            [$startMonth, $endMonth] = $quarterMonths[$quarter];
        } elseif ($fromMonth && $toMonth) {
            $startMonth = $fromMonth;
            $endMonth = $toMonth;
        } else {
            // Year only - full year
            $startMonth = 1;
            $endMonth = 12;
        }

        // Build date range for query
        $startDate = Carbon::create($year, $startMonth, 1)->startOfMonth();
        $endDate = Carbon::create($year, $endMonth, 1)->endOfMonth();

        Log::info('Generating blood test summary', [
            'year' => $year,
            'quarter' => $quarter,
            'from_month' => $fromMonth,
            'to_month' => $toMonth,
            'date_range' => [$startDate->toDateString(), $endDate->toDateString()],
        ]);

        // Get total ALL results in period (before filtering)
        $totalAllResults = TestResult::whereBetween('collected_date', [$startDate, $endDate])
            ->where('is_completed', true)
            ->count();

        // Query TestResults with eager-loaded items filtered to required panel IDs
        $results = TestResult::with([
            'testResultItems' => function ($query) {
                $query->whereIn('panel_panel_item_id', PanelPanelItem::ALL_IDS);
            },
            'patient',
        ])
            ->whereBetween('collected_date', [$startDate, $endDate])
            ->where('is_completed', true)
            ->get();

        // Filter in-memory: only keep results that have at least one item from EACH category
        $filteredResults = $results->filter(function ($result) {
            return $this->hasAllRequiredCategories($result->testResultItems);
        });

        Log::info('Blood test summary filtered for required categories', [
            'year' => $year,
            'total_all_results' => $totalAllResults,
            'total_before_filter' => $results->count(),
            'total_after_filter' => $filteredResults->count(),
        ]);

        // Prepare data for batch BMI lookup
        $icReferenceDates = [];
        foreach ($filteredResults as $result) {
            if ($result->patient && $result->patient->icno) {
                $icReferenceDates[] = [
                    'ic' => $result->patient->icno,
                    'reference_date' => $result->collected_date
                        ? Carbon::parse($result->collected_date)->format('Y-m-d')
                        : null,
                ];
            }
        }

        // Batch lookup BMI values
        $bmiValues = $this->myHealthService->getPatientBMIBatch($icReferenceDates);

        // Build evaluatable data for condition checking
        $evaluatableData = $filteredResults->map(function ($result) use ($bmiValues) {
            $patient = $result->patient;
            $collectedDate = $result->collected_date ? Carbon::parse($result->collected_date) : null;

            return [
                'tc' => $this->getPanelValue($result->testResultItems, 'tc'),
                'ldlc' => $this->getPanelValue($result->testResultItems, 'ldlc'),
                'egfr' => $this->getPanelValue($result->testResultItems, 'egfr'),
                'hba1c_percent' => $this->getPanelValue($result->testResultItems, 'hba1c_percent'),
                'alt' => $this->getPanelValue($result->testResultItems, 'alt'),
                'age' => $this->resolvePatientAge($patient, $collectedDate),
                'bmi' => $patient && $patient->icno ? ($bmiValues[$patient->icno] ?? null) : null,
            ];
        });

        // Evaluate all conditions
        $conditionStatistics = $this->conditionEvaluator->evaluateAll(
            $evaluatableData,
            $totalAllResults,
            $filteredResults->count()
        );

        Log::info('Blood test summary generated successfully', [
            'year' => $year,
            'total_all_results' => $totalAllResults,
            'total_filtered_results' => $filteredResults->count(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'year' => $year,
                    'quarter' => $quarter,
                    'from_month' => $startMonth,
                    'to_month' => $endMonth,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                'total_all_results' => $totalAllResults,
                'total_filtered_results' => $filteredResults->count(),
                'conditions' => $conditionStatistics,
            ],
        ]);
    }

    /**
     * Check if the test result items contain at least one item from EACH required category
     */
    private function hasAllRequiredCategories($items): bool
    {
        $itemIds = $items->pluck('panel_panel_item_id')->toArray();

        foreach (PanelPanelItem::REQUIRED_CATEGORIES as $category => $categoryIds) {
            $hasCategory = ! empty(array_intersect($itemIds, $categoryIds));
            if (! $hasCategory) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve patient age from stored value or calculate from DOB
     */
    private function resolvePatientAge($patient, ?Carbon $collectedDate): ?int
    {
        if (! $patient) {
            return null;
        }

        if ($patient->age !== null) {
            return (int) $patient->age;
        }

        if ($patient->dob && $collectedDate) {
            return calculatePatientAge($patient->dob, $collectedDate->format('Y-m-d'));
        }

        return null;
    }

    /**
     * Get numeric panel value by category
     */
    private function getPanelValue($items, string $category): ?float
    {
        $categoryIds = PanelPanelItem::REQUIRED_CATEGORIES[$category] ?? [];
        $item = $items->first(fn ($i) => in_array($i->panel_panel_item_id, $categoryIds));

        return $item && is_numeric($item->value) ? (float) $item->value : null;
    }

    /**
     * Generate PDF export of blood test result summary.
     *
     * Uses the same filtering logic as summary() but outputs as PDF.
     * Stores PDF to storage/app/public/pdf directory.
     */
    public function summaryPdf(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:1900|max:2100',
            'quarter' => 'nullable|integer|in:1,2,3,4|prohibits:from_month,to_month',
            'from_month' => 'nullable|integer|min:1|max:12|required_with:to_month|prohibits:quarter',
            'to_month' => 'nullable|integer|min:1|max:12|required_with:from_month|gte:from_month',
        ]);

        $year = $validated['year'];
        $quarter = $validated['quarter'] ?? null;
        $fromMonth = $validated['from_month'] ?? null;
        $toMonth = $validated['to_month'] ?? null;

        $quarterMonths = [
            1 => [1, 3],
            2 => [4, 6],
            3 => [7, 9],
            4 => [10, 12],
        ];

        if ($quarter) {
            [$startMonth, $endMonth] = $quarterMonths[$quarter];
        } elseif ($fromMonth && $toMonth) {
            $startMonth = $fromMonth;
            $endMonth = $toMonth;
        } else {
            $startMonth = 1;
            $endMonth = 12;
        }

        $startDate = Carbon::create($year, $startMonth, 1)->startOfMonth();
        $endDate = Carbon::create($year, $endMonth, 1)->endOfMonth();

        Log::info('Generating blood test summary PDF', [
            'year' => $year,
            'quarter' => $quarter,
            'from_month' => $fromMonth,
            'to_month' => $toMonth,
            'date_range' => [$startDate->toDateString(), $endDate->toDateString()],
        ]);

        try {
            $totalAllResults = TestResult::whereBetween('collected_date', [$startDate, $endDate])
                ->where('is_completed', true)
                ->count();

            $results = TestResult::with([
                'testResultItems' => function ($query) {
                    $query->whereIn('panel_panel_item_id', PanelPanelItem::ALL_IDS);
                },
                'patient',
            ])
                ->whereBetween('collected_date', [$startDate, $endDate])
                ->where('is_completed', true)
                ->get();

            $filteredResults = $results->filter(function ($result) {
                return $this->hasAllRequiredCategories($result->testResultItems);
            });

            $icReferenceDates = [];
            foreach ($filteredResults as $result) {
                if ($result->patient && $result->patient->icno) {
                    $icReferenceDates[] = [
                        'ic' => $result->patient->icno,
                        'reference_date' => $result->collected_date
                            ? Carbon::parse($result->collected_date)->format('Y-m-d')
                            : null,
                    ];
                }
            }

            $bmiValues = $this->myHealthService->getPatientBMIBatch($icReferenceDates);

            $evaluatableData = $filteredResults->map(function ($result) use ($bmiValues) {
                $patient = $result->patient;
                $collectedDate = $result->collected_date ? Carbon::parse($result->collected_date) : null;

                return [
                    'tc' => $this->getPanelValue($result->testResultItems, 'tc'),
                    'ldlc' => $this->getPanelValue($result->testResultItems, 'ldlc'),
                    'egfr' => $this->getPanelValue($result->testResultItems, 'egfr'),
                    'hba1c_percent' => $this->getPanelValue($result->testResultItems, 'hba1c_percent'),
                    'alt' => $this->getPanelValue($result->testResultItems, 'alt'),
                    'age' => $this->resolvePatientAge($patient, $collectedDate),
                    'bmi' => $patient && $patient->icno ? ($bmiValues[$patient->icno] ?? null) : null,
                ];
            });

            $conditionStatistics = $this->conditionEvaluator->evaluateAll(
                $evaluatableData,
                $totalAllResults,
                $filteredResults->count()
            );

            $periodLabel = $this->buildPeriodLabel($year, $quarter, $startMonth, $endMonth);
            $generatedAt = Carbon::now()->format('Y-m-d H:i:s');

            $html = $this->buildSummaryPdfHtml(
                $periodLabel,
                $generatedAt,
                $totalAllResults,
                $filteredResults->count(),
                $conditionStatistics
            );

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_top' => 15,
                'margin_bottom' => 15,
                'margin_left' => 15,
                'margin_right' => 15,
                'default_font' => 'Arial',
            ]);

            $mpdf->SetTitle('Blood Test Summary Report');
            $mpdf->WriteHTML($html);

            $pdfContent = $mpdf->Output('', 'S');

            $filename = 'blood_test_summary_'.$year.'_'.time().'.pdf';
            $storagePath = 'public/pdf/'.$filename;

            Storage::put($storagePath, $pdfContent);

            $publicUrl = Storage::url('pdf/'.$filename);

            Log::info('Blood test summary PDF generated and stored', [
                'year' => $year,
                'total_all_results' => $totalAllResults,
                'total_filtered_results' => $filteredResults->count(),
                'storage_path' => $storagePath,
                'public_url' => $publicUrl,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'filename' => $filename,
                    'path' => $storagePath,
                    'url' => $publicUrl,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Failed to generate blood test summary PDF', [
                'year' => $year,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate PDF export for web access (no auth required).
     *
     * Accepts GET query parameters and returns inline PDF.
     * URL: /consult-call/summary/pdf?year=2025&quarter=4
     */
    public function summaryPdfWeb(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:1900|max:2100',
            'quarter' => 'nullable|integer|in:1,2,3,4',
            'from_month' => 'nullable|integer|min:1|max:12',
            'to_month' => 'nullable|integer|min:1|max:12|gte:from_month',
        ]);

        $year = $validated['year'];
        $quarter = $validated['quarter'] ?? null;
        $fromMonth = $validated['from_month'] ?? null;
        $toMonth = $validated['to_month'] ?? null;

        $quarterMonths = [
            1 => [1, 3],
            2 => [4, 6],
            3 => [7, 9],
            4 => [10, 12],
        ];

        if ($quarter) {
            [$startMonth, $endMonth] = $quarterMonths[$quarter];
        } elseif ($fromMonth && $toMonth) {
            $startMonth = $fromMonth;
            $endMonth = $toMonth;
        } else {
            $startMonth = 1;
            $endMonth = 12;
        }

        $startDate = Carbon::create($year, $startMonth, 1)->startOfMonth();
        $endDate = Carbon::create($year, $endMonth, 1)->endOfMonth();

        Log::info('Generating blood test summary PDF (web)', [
            'year' => $year,
            'quarter' => $quarter,
            'date_range' => [$startDate->toDateString(), $endDate->toDateString()],
        ]);

        try {
            $totalAllResults = TestResult::whereBetween('collected_date', [$startDate, $endDate])
                ->where('is_completed', true)
                ->count();

            $results = TestResult::with([
                'testResultItems' => function ($query) {
                    $query->whereIn('panel_panel_item_id', PanelPanelItem::ALL_IDS);
                },
                'patient',
            ])
                ->whereBetween('collected_date', [$startDate, $endDate])
                ->where('is_completed', true)
                ->get();

            $filteredResults = $results->filter(function ($result) {
                return $this->hasAllRequiredCategories($result->testResultItems);
            });

            $icReferenceDates = [];
            foreach ($filteredResults as $result) {
                if ($result->patient && $result->patient->icno) {
                    $icReferenceDates[] = [
                        'ic' => $result->patient->icno,
                        'reference_date' => $result->collected_date
                            ? Carbon::parse($result->collected_date)->format('Y-m-d')
                            : null,
                    ];
                }
            }

            $bmiValues = $this->myHealthService->getPatientBMIBatch($icReferenceDates);

            // Debug: Log BMI lookup results
            $bmiCount = count(array_filter($bmiValues, fn ($v) => $v !== null));
            Log::info('BMI lookup results', [
                'total_ic_lookups' => count($icReferenceDates),
                'bmi_values_found' => $bmiCount,
                'sample_bmi_values' => array_slice($bmiValues, 0, 5, true),
            ]);

            $evaluatableData = $filteredResults->map(function ($result) use ($bmiValues) {
                $patient = $result->patient;
                $collectedDate = $result->collected_date ? Carbon::parse($result->collected_date) : null;

                return [
                    'tc' => $this->getPanelValue($result->testResultItems, 'tc'),
                    'ldlc' => $this->getPanelValue($result->testResultItems, 'ldlc'),
                    'egfr' => $this->getPanelValue($result->testResultItems, 'egfr'),
                    'hba1c_percent' => $this->getPanelValue($result->testResultItems, 'hba1c_percent'),
                    'alt' => $this->getPanelValue($result->testResultItems, 'alt'),
                    'age' => $this->resolvePatientAge($patient, $collectedDate),
                    'bmi' => $patient && $patient->icno ? ($bmiValues[$patient->icno] ?? null) : null,
                ];
            });

            $conditionStatistics = $this->conditionEvaluator->evaluateAll(
                $evaluatableData,
                $totalAllResults,
                $filteredResults->count()
            );

            $periodLabel = $this->buildPeriodLabel($year, $quarter, $startMonth, $endMonth);
            $generatedAt = Carbon::now()->format('Y-m-d H:i:s');

            $html = $this->buildSummaryPdfHtml(
                $periodLabel,
                $generatedAt,
                $totalAllResults,
                $filteredResults->count(),
                $conditionStatistics
            );

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_top' => 15,
                'margin_bottom' => 15,
                'margin_left' => 15,
                'margin_right' => 15,
                'default_font' => 'Arial',
            ]);

            $mpdf->SetTitle('Blood Test Summary Report');
            $mpdf->WriteHTML($html);

            $pdfContent = $mpdf->Output('', 'S');

            Log::info('Blood test summary PDF generated (web)', [
                'year' => $year,
                'total_all_results' => $totalAllResults,
                'total_filtered_results' => $filteredResults->count(),
            ]);

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="blood_test_summary_'.$year.'.pdf"',
            ]);

        } catch (Exception $e) {
            Log::error('Failed to generate blood test summary PDF (web)', [
                'year' => $year,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build period label for PDF header.
     */
    private function buildPeriodLabel(int $year, ?int $quarter, int $startMonth, int $endMonth): string
    {
        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        if ($quarter) {
            return "Q{$quarter} {$year} ({$monthNames[$startMonth]} - {$monthNames[$endMonth]})";
        }

        if ($startMonth === 1 && $endMonth === 12) {
            return "Full Year {$year}";
        }

        return "{$monthNames[$startMonth]} - {$monthNames[$endMonth]} {$year}";
    }

    /**
     * Build HTML content for summary PDF.
     */
    private function buildSummaryPdfHtml(
        string $periodLabel,
        string $generatedAt,
        int $totalAllResults,
        int $totalFilteredResults,
        array $conditionStatistics
    ): string {
        $tableRows = '';
        foreach ($conditionStatistics as $condition) {
            $tableRows .= sprintf(
                '<tr>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">%d</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">%s</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">%s</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">%s%%</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">%s%%</td>
                </tr>',
                $condition['condition_id'],
                htmlspecialchars($condition['condition_description']),
                number_format($condition['total_met']),
                number_format($condition['percentage_of_filtered'], 2),
                number_format($condition['percentage_of_total'], 2)
            );
        }

        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Blood Test Summary Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #333;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 18px;
            color: #222;
        }
        .header p {
            margin: 3px 0;
            font-size: 12px;
            color: #555;
        }
        .summary-box {
            background-color: #f5f5f5;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
        .summary-box p {
            margin: 5px 0;
            font-size: 12px;
        }
        .summary-box strong {
            color: #222;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        th {
            background-color: #4a4a4a;
            color: white;
            padding: 10px 8px;
            border: 1px solid #333;
            text-align: left;
        }
        th.center {
            text-align: center;
        }
        th.right {
            text-align: right;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Blood Test Summary Report</h1>
        <p>Period: '.htmlspecialchars($periodLabel).'</p>
        <p>Generated: '.htmlspecialchars($generatedAt).'</p>
    </div>

    <div class="summary-box">
        <p><strong>Total All Results:</strong> '.number_format($totalAllResults).'</p>
        <p><strong>Total Filtered Results:</strong> '.number_format($totalFilteredResults).'</p>
    </div>

    <table>
        <thead>
            <tr>
                <th class="center" style="width: 30px;">#</th>
                <th style="width: auto;">Condition Description</th>
                <th class="right" style="width: 70px;">Total Met</th>
                <th class="right" style="width: 70px;">% Filtered</th>
                <th class="right" style="width: 70px;">% Total</th>
            </tr>
        </thead>
        <tbody>
            '.$tableRows.'
        </tbody>
    </table>
</body>
</html>';
    }
}

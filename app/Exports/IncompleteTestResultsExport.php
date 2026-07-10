<?php

namespace App\Exports;

use App\Models\IncompleteTestResult;
use App\Models\TestResult;
use App\Services\PanelCompletenessService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class IncompleteTestResultsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(protected PanelCompletenessService $panelCompletenessService)
    {
    }

    public function query(): Builder
    {
        return IncompleteTestResult::query()
            ->join('test_results', 'test_results.id', '=', 'incomplete_test_results.test_result_id')
            ->whereNull('test_results.deleted_at')
            ->select('test_results.id as test_result_id', 'test_results.ref_id', 'test_results.lab_no', 'incomplete_test_results.reason', 'incomplete_test_results.missing_details')
            ->orderBy('incomplete_test_results.id');
    }

    public function headings(): array
    {
        return ['ref_id', 'lab_no', 'reason', 'missing_details'];
    }

    public function map($row): array
    {
        $missingDetails = $row->missing_details ?? '';

        if ($row->reason === 'invoice_mismatch') {
            $missingDetails = $this->withPackageNames($missingDetails, $row->test_result_id);
        }

        return [
            $row->ref_id ?? '',
            $row->lab_no,
            $row->reason ?? '',
            $missingDetails,
        ];
    }

    /**
     * Append ODB package/profile names to an invoice_mismatch row's
     * missing_details, fetched on demand from PanelCompletenessService rather
     * than stored on the incomplete_test_results row - this lookup is only
     * needed here, at export time.
     */
    private function withPackageNames(string $missingDetails, int $testResultId): string
    {
        $testResult = TestResult::find($testResultId);

        if (! $testResult) {
            return $missingDetails;
        }

        $packageNames = $this->panelCompletenessService->getInvoicePackageNames($testResult);

        if (empty($packageNames)) {
            return $missingDetails;
        }

        return $missingDetails.'; ODB package(s): '.implode(', ', $packageNames);
    }
}

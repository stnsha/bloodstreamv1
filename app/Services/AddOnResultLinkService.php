<?php

namespace App\Services;

use App\Models\ClinicalCondition;
use App\Models\ConsultCall;
use App\Models\ConsultCallAddOn;
use App\Models\ConsultCallAddOnResult;
use App\Models\ConsultCallDetails;
use App\Models\TestResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Links Add-On invoices and their lab reports back to the consult call that
 * recommended the add-ons.
 *
 * A consult call can recommend several add-ons (consult_call_add_ons). When
 * Customer Support syncs an invoice, every recommended add-on whose item_code is on
 * that invoice records the ODB blood_test_sales.id; several add-ons can share it.
 * The lab returns that id inside test_results.ref_id as <lab code><id> (e.g.
 * INN123500) and compiles the whole sale under one lab number.
 *
 * The report is linked from TestResultCompletionDispatcher, which only runs once the
 * existing completeness check has passed, so a linked report means every panel has
 * arrived: the add-ons on that sale become Completed.
 */
class AddOnResultLinkService
{
    /**
     * Record a synced invoice on the consult call's recommended add-ons it contains.
     *
     * Only add-ons without a sale yet are assigned, so an add-on bought on an earlier
     * invoice keeps it. If the lab already delivered the report, it is linked now.
     *
     * @param  array<int, string>  $matchedItemCodes  recommended item codes found on the invoice
     * @return Collection<int, ConsultCallAddOn> the consult call's add-on rows after assignment
     */
    public function assignInvoice(
        ConsultCall $consultCall,
        ?int $detailId,
        string $invoiceId,
        int $bloodTestSalesId,
        array $matchedItemCodes,
        array $selectedAddOnIds = []
    ): Collection {
        $context = [
            'consult_call_id' => $consultCall->id,
            'detail_id' => $detailId,
            'invoice_id' => $invoiceId,
            'blood_test_sales_id' => $bloodTestSalesId,
            'matched_item_codes' => $matchedItemCodes,
            'selected_add_on_ids' => $selectedAddOnIds,
        ];

        Log::info('AddOnResultLinkService: assigning add-on invoice', $context);

        try {
            DB::beginTransaction();

            // Add-ons ticked on the form but not saved yet (Sync before Submit): create
            // their rows so they can take the invoice. Never removes a selection here.
            foreach (array_unique(array_map('intval', $selectedAddOnIds)) as $addOnId) {
                ConsultCallAddOn::firstOrCreate(['consult_call_id' => $consultCall->id, 'add_on_id' => $addOnId]);
            }

            $rows = ConsultCallAddOn::where('consult_call_id', $consultCall->id)
                ->whereNull('blood_test_sales_id')
                ->whereHas('addOn', fn ($q) => $q->whereIn('item_code', $matchedItemCodes))
                ->get();

            foreach ($rows as $row) {
                $row->update([
                    'consult_call_detail_id' => $detailId,
                    'invoice_id' => $invoiceId,
                    'blood_test_sales_id' => $bloodTestSalesId,
                    'invoice_status' => ConsultCallAddOn::INVOICE_STATUS_CONFIRMED,
                ]);
            }

            DB::commit();

            Log::info('AddOnResultLinkService: add-on invoice assigned', $context + [
                'assigned_add_on_ids' => $rows->pluck('add_on_id')->all(),
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('AddOnResultLinkService: failed to assign add-on invoice', $context + [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        // The report may already be here (rare); link it without failing the assignment.
        try {
            $this->linkExistingResult($bloodTestSalesId);
        } catch (Throwable $e) {
            Log::error('AddOnResultLinkService: reverse link after invoice assignment failed', $context + [
                'error' => $e->getMessage(),
            ]);
        }

        return ConsultCallAddOn::with(['addOn', 'results.testResult:id,lab_no,reported_date'])
            ->where('consult_call_id', $consultCall->id)
            ->get();
    }

    /**
     * Purchased add-on rows that a new selection would drop (these cannot be unselected).
     *
     * @param  array<int, int>  $addOnIds  complete new selection
     * @return Collection<int, ConsultCallAddOn>
     */
    public function purchasedOutsideSelection(ConsultCall $consultCall, array $addOnIds): Collection
    {
        return ConsultCallAddOn::where('consult_call_id', $consultCall->id)
            ->whereNotIn('add_on_id', $addOnIds)
            ->whereNotNull('blood_test_sales_id')
            ->get();
    }

    /**
     * Apply the complete add-on selection without touching rows that stay selected, so
     * their invoice data survives. Runs inside the caller's transaction; callers check
     * purchasedOutsideSelection() first.
     *
     * @param  array<int, int>  $addOnIds  complete new selection
     */
    public function syncSelection(ConsultCall $consultCall, array $addOnIds): void
    {
        $existingIds = ConsultCallAddOn::where('consult_call_id', $consultCall->id)->pluck('add_on_id')->all();

        ConsultCallAddOn::where('consult_call_id', $consultCall->id)
            ->whereNotIn('add_on_id', $addOnIds)
            ->whereNull('blood_test_sales_id')
            ->delete();

        foreach (array_diff(array_unique($addOnIds), $existingIds) as $addOnId) {
            ConsultCallAddOn::create(['consult_call_id' => $consultCall->id, 'add_on_id' => $addOnId]);
        }

        Log::info('AddOnResultLinkService: add-on selection synced', [
            'consult_call_id' => $consultCall->id,
            'selected_add_on_ids' => array_values(array_unique($addOnIds)),
        ]);
    }

    /**
     * Forward link: a completed result has arrived, attach it to the consult call
     * whose add-on invoice produced it.
     */
    public function linkFromTestResult(TestResult $testResult): void
    {
        $bloodTestSalesId = $this->extractBloodTestSalesId($testResult->ref_id);

        if ($bloodTestSalesId === null) {
            return;
        }

        $addOnRows = ConsultCallAddOn::with('consultCall:id,patient_id')
            ->where('blood_test_sales_id', $bloodTestSalesId)
            ->get();

        if ($addOnRows->isEmpty()) {
            return;
        }

        $this->link($addOnRows, $testResult, $bloodTestSalesId, 'result_arrived');
    }

    /**
     * Reverse link: an invoice was just synced; attach its completed result if the
     * lab already delivered it.
     */
    public function linkExistingResult(int $bloodTestSalesId): void
    {
        $addOnRows = ConsultCallAddOn::with('consultCall:id,patient_id')
            ->where('blood_test_sales_id', $bloodTestSalesId)
            ->get();

        if ($addOnRows->isEmpty()) {
            return;
        }

        // Narrow with LIKE, then confirm the exact <prefix><id> shape in PHP so
        // INN123500 never matches INN1123500.
        $testResult = TestResult::where('ref_id', 'like', '%'.$bloodTestSalesId)
            ->where('is_completed', true)
            ->orderByDesc('id')
            ->get()
            ->first(fn (TestResult $candidate) => $this->extractBloodTestSalesId($candidate->ref_id) === $bloodTestSalesId);

        if (! $testResult) {
            return;
        }

        $this->link($addOnRows, $testResult, $bloodTestSalesId, 'invoice_synced');
    }

    /**
     * Add-on role of each ODB blood test row, for the blood_test list's Add-On column.
     * Batched: one query per kind for the whole page.
     *
     *  - 'sale'        : the row IS an add-on purchase (consult_call_add_ons.blood_test_sales_id).
     *  - 'recommended' : the row's result triggered an AO / CC + AO recommendation
     *                    (consult_call_details.test_result_id); summarised over that
     *                    consult call's recommended add-ons.
     * 'sale' wins when a row is both. status: null (nothing bought), 1 Confirmed,
     * 2 Completed (every bought add-on has its report).
     *
     * @param  array<int, string>  $refIds  ODB ref_ids (<lab code><blood_test_sales.id>)
     * @return array<string, array{role: string, status: int|null, consult_call_id: int, bought: int, selected: int}>
     */
    public function resolveRowRoles(array $refIds): array
    {
        $refIds = array_values(array_unique(array_filter($refIds, fn ($r) => is_string($r) && $r !== '')));

        $saleIdByRefId = [];
        foreach ($refIds as $refId) {
            $saleId = $this->extractBloodTestSalesId($refId);
            if ($saleId !== null) {
                $saleIdByRefId[$refId] = $saleId;
            }
        }

        $roles = [];

        // Recommended: rows whose result is on an AO / CC + AO consult-call detail.
        $testResultIdsByRefId = TestResult::whereIn('ref_id', $refIds)
            ->get(['id', 'ref_id'])
            ->groupBy('ref_id')
            ->map(fn ($rows) => $rows->pluck('id')->all());

        $allTestResultIds = $testResultIdsByRefId->flatten()->all();
        $consultCallIdByRefId = [];

        if (! empty($allTestResultIds)) {
            $details = ConsultCallDetails::whereIn('test_result_id', $allTestResultIds)
                ->orderByDesc('id')
                ->get(['id', 'consult_call_id', 'clinical_condition_id', 'test_result_id']);

            // Read types directly (not ClinicalCondition::getCondition(), which only covers
            // ACTIVE conditions): a recommendation from a since-deactivated condition still counts.
            $conditionTypes = ClinicalCondition::whereIn('id', $details->pluck('clinical_condition_id')->unique()->all())
                ->pluck('type', 'id');

            $aoDetailsByTestResult = $details
                ->filter(fn (ConsultCallDetails $detail) => in_array($conditionTypes[$detail->clinical_condition_id] ?? null, ['AO', 'CC + AO'], true))
                ->groupBy('test_result_id');

            foreach ($testResultIdsByRefId as $refId => $testResultIds) {
                $latest = collect($testResultIds)
                    ->flatMap(fn ($id) => $aoDetailsByTestResult->get($id, collect()))
                    ->sortByDesc('id')
                    ->first();

                if ($latest) {
                    $consultCallIdByRefId[$refId] = (int) $latest->consult_call_id;
                }
            }
        }

        // Sale rows: add-on rows bought on that sale.
        $saleRows = empty($saleIdByRefId)
            ? collect()
            : ConsultCallAddOn::whereIn('blood_test_sales_id', array_values($saleIdByRefId))->get();

        // Everything needed for the summaries, in two queries.
        $consultCallIds = array_unique(array_merge(
            array_values($consultCallIdByRefId),
            $saleRows->pluck('consult_call_id')->all()
        ));

        $addOnsByConsultCall = empty($consultCallIds)
            ? collect()
            : ConsultCallAddOn::whereIn('consult_call_id', $consultCallIds)->get()->groupBy('consult_call_id');

        $linkedSaleIds = ConsultCallAddOnResult::whereIn(
            'blood_test_sales_id',
            $addOnsByConsultCall->flatten()->pluck('blood_test_sales_id')->filter()->unique()->all()
        )->pluck('blood_test_sales_id')->unique()->flip();

        foreach ($consultCallIdByRefId as $refId => $consultCallId) {
            $roles[$refId] = ['role' => 'recommended', 'consult_call_id' => $consultCallId]
                + $this->summarise($addOnsByConsultCall->get($consultCallId, collect()), $linkedSaleIds);
        }

        foreach ($saleIdByRefId as $refId => $saleId) {
            $rowsForSale = $saleRows->where('blood_test_sales_id', $saleId);
            if ($rowsForSale->isEmpty()) {
                continue;
            }

            $roles[$refId] = [
                'role' => 'sale',
                'consult_call_id' => (int) $rowsForSale->first()->consult_call_id,
                'status' => isset($linkedSaleIds[$saleId])
                    ? ConsultCallAddOn::INVOICE_STATUS_COMPLETED
                    : ConsultCallAddOn::INVOICE_STATUS_CONFIRMED,
                'bought' => $rowsForSale->count(),
                'selected' => $addOnsByConsultCall->get($rowsForSale->first()->consult_call_id, collect())->count(),
            ];
        }

        return $roles;
    }

    /**
     * Return the blood_test_sales.id carried in a ref_id (<letters><digits>), or null.
     */
    public function extractBloodTestSalesId(?string $refId): ?int
    {
        if ($refId === null || ! preg_match('/^[A-Z]+(\d+)$/i', trim($refId), $matches)) {
            return null;
        }

        $id = (int) $matches[1];

        return $id > 0 ? $id : null;
    }

    /**
     * @param  Collection<int, ConsultCallAddOn>  $addOns
     * @return array{status: int|null, bought: int, selected: int}
     */
    private function summarise(Collection $addOns, Collection $linkedSaleIds): array
    {
        $bought = $addOns->filter(fn (ConsultCallAddOn $row) => $row->blood_test_sales_id !== null);
        $completed = $bought->filter(fn (ConsultCallAddOn $row) => isset($linkedSaleIds[$row->blood_test_sales_id]));

        $status = null;
        if ($bought->isNotEmpty()) {
            $status = $completed->count() === $bought->count()
                ? ConsultCallAddOn::INVOICE_STATUS_COMPLETED
                : ConsultCallAddOn::INVOICE_STATUS_CONFIRMED;
        }

        return [
            'status' => $status,
            'bought' => $bought->count(),
            'selected' => $addOns->count(),
        ];
    }

    /**
     * @param  Collection<int, ConsultCallAddOn>  $addOnRows  add-on rows sharing this sale id
     */
    private function link(Collection $addOnRows, TestResult $testResult, int $bloodTestSalesId, string $trigger): void
    {
        $first = $addOnRows->first();

        $context = [
            'trigger' => $trigger,
            'test_result_id' => $testResult->id,
            'ref_id' => $testResult->ref_id,
            'blood_test_sales_id' => $bloodTestSalesId,
            'consult_call_id' => $first->consult_call_id,
            'add_on_ids' => $addOnRows->pluck('add_on_id')->all(),
        ];

        Log::info('AddOnResultLinkService: linking add-on result to consult call', $context);

        // The sale id is an exact match, so the link is kept even when patient
        // matching disagrees; logged at error level so it is visible on production.
        $consultCallPatientId = $first->consultCall?->patient_id;
        if ($testResult->patient_id && $consultCallPatientId && (int) $testResult->patient_id !== (int) $consultCallPatientId) {
            Log::error('AddOnResultLinkService: patient mismatch between add-on result and consult call, linking anyway', $context + [
                'test_result_patient_id' => $testResult->patient_id,
                'consult_call_patient_id' => $consultCallPatientId,
            ]);
        }

        try {
            DB::beginTransaction();

            ConsultCallAddOnResult::firstOrCreate(
                ['test_result_id' => $testResult->id],
                [
                    'consult_call_id' => $first->consult_call_id,
                    'consult_call_detail_id' => $first->consult_call_detail_id,
                    'blood_test_sales_id' => $bloodTestSalesId,
                ]
            );

            ConsultCallAddOn::where('blood_test_sales_id', $bloodTestSalesId)
                ->update(['invoice_status' => ConsultCallAddOn::INVOICE_STATUS_COMPLETED]);

            DB::commit();

            Log::info('AddOnResultLinkService: add-on result linked, add-ons completed', $context);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('AddOnResultLinkService: failed to link add-on result', $context + [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

<?php

namespace App\Services;

use App\Models\TestResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifies that a lab ref_id (PlacerOrderNumber = ODB blood_test_sales.id)
 * actually belongs to the patient on the lab result before it is stored.
 *
 * A mistyped ref_id (e.g. INN124856 instead of INN124586) can point at a real
 * sale of a different customer. Every downstream lookup keyed on ref_id
 * (consult-call customer/outlet, invoice completeness) then silently attaches
 * the result to the wrong person. This service compares the ref_id owner's IC
 * and name with the patient's; on a mismatch it looks for the patient's own
 * sale by IC and substitutes it. A missing ref_id is filled the same way.
 *
 * Outcomes (the 'status' key of verify()):
 *  - verified:    ref_id owner matches the patient (same IC, or IC typo plus same
 *                 name or same date of birth); ref_id kept
 *  - corrected:   mismatch; exactly one unused sale found for the patient IC
 *                 whose campaign date equals the collected date; ref_id replaced
 *  - filled:      no ref_id delivered; exactly one unused sale found for the
 *                 patient IC on the collected date; ref_id set from it
 *  - unresolved:  mismatch; no unique replacement; ref_id dropped (null) so it
 *                 cannot link the result to the wrong customer
 *  - not_found:   ref_id does not resolve to a sale and no unique replacement; ref_id kept
 *  - skipped:     nothing to verify (no usable patient IC, no lab code, or no
 *                 ref_id and no unique sale to fill it from)
 *  - unavailable: ODB lookup failed; ref_id kept (fail-open)
 *
 * For verified / corrected / filled the ODB customer behind the sale is returned
 * as customer_name / customer_ic: the name registered at the sale is used as the
 * patient name. ic_conflict is true when that customer's IC differs from the
 * delivered IC (accepted as the same person); the caller records it for manual
 * review instead of choosing either IC.
 */
class RefIdVerificationService
{
    /**
     * Minimum similar_text() percentage for two names to count as the same person.
     */
    const NAME_SIMILARITY_THRESHOLD = 85.0;

    /**
     * Maximum IC edit distance still treated as a typo of the same IC (only
     * accepted together with a matching name or date of birth).
     */
    const MAX_IC_TYPO_DISTANCE = 2;

    /**
     * Definitive outcomes are cached so incremental Innoquest deliveries of the
     * same lab_no do not repeat the ODB round trip.
     */
    const CACHE_TTL_SECONDS = 21600;

    /**
     * Statuses whose customer_name / customer_ic identify the patient.
     */
    const IDENTIFIED_STATUSES = ['verified', 'corrected', 'filled'];

    protected OctopusApiService $octopusApi;

    protected IcNormalizerService $icNormalizer;

    public function __construct(OctopusApiService $octopusApi, IcNormalizerService $icNormalizer)
    {
        $this->octopusApi = $octopusApi;
        $this->icNormalizer = $icNormalizer;
    }

    /**
     * @return array{ref_id: string|null, status: string, customer_name: string|null, customer_ic: string|null, ic_conflict: bool}
     */
    public function verify(
        ?string $refId,
        ?string $labCode,
        ?string $patientIc,
        ?string $patientName,
        ?string $collectedDate,
        ?string $labNo,
        ?string $patientDob = null
    ): array {
        if (! $labCode || ! $patientIc || str_starts_with($patientIc, 'N/A_')) {
            return $this->outcome($refId, 'skipped');
        }

        // Without a ref_id the only thing to do is fill it, which needs the collected date.
        if (! $refId && ! $collectedDate) {
            return $this->outcome(null, 'skipped');
        }

        $cacheKey = 'ref_id_verification_v2_'.md5(implode('|', [$labNo, $refId, $patientIc, $collectedDate]));
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached + $this->outcome($refId, 'skipped');
        }

        $context = [
            'ref_id' => $refId,
            'lab_no' => $labNo,
            'patient_ic' => $patientIc,
            'patient_name' => $patientName,
            'collected_date' => $collectedDate,
        ];

        Log::info('RefIdVerificationService: Verifying ref_id against patient', $context);

        try {
            $data = $this->octopusApi->verifyRefIdByIc($refId, $patientIc, $labCode);
        } catch (Throwable $e) {
            Log::error('RefIdVerificationService: ODB lookup failed, keeping ref_id unverified', $context + [
                'error' => $e->getMessage(),
            ]);

            return $this->outcome($refId, 'unavailable');
        }

        if ($data === null) {
            Log::error('RefIdVerificationService: ODB lookup returned no data, keeping ref_id unverified', $context);

            return $this->outcome($refId, 'unavailable');
        }

        if (! $refId) {
            return $this->remember($cacheKey, $this->fillMissingRefId($data['ic_sales'], $labCode, $patientIc, $collectedDate, $labNo, $context));
        }

        $refCustomer = $data['ref_customer'];

        if ($refCustomer && $this->isSamePerson($refCustomer, $patientIc, $patientName, $patientDob)) {
            Log::info('RefIdVerificationService: ref_id verified', $context + [
                'customer_id' => $refCustomer['customer_id'] ?? null,
            ]);

            return $this->remember($cacheKey, $this->identifiedOutcome($refId, 'verified', $refCustomer, $patientIc));
        }

        $mismatchContext = $context + [
            'ref_customer_id' => $refCustomer['customer_id'] ?? null,
            'ref_customer_name' => $refCustomer['customer_name'] ?? null,
            'ref_customer_ic' => $refCustomer['ic'] ?? null,
        ];

        $candidates = $this->candidateSales($data['ic_sales'], $labCode, $collectedDate, $labNo);

        if (count($candidates) === 1) {
            $sale = $candidates[0];
            $correctedRefId = strtoupper($labCode.$sale['blood_test_sales_id']);

            // error level so the correction stays visible under production LOG_LEVEL=error
            Log::error('RefIdVerificationService: ref_id belongs to a different customer, corrected via patient IC', $mismatchContext + [
                'corrected_ref_id' => $correctedRefId,
                'corrected_customer_id' => $sale['customer_id'] ?? null,
                'corrected_sale_date' => $sale['date'] ?? null,
                'corrected_campaign_id' => $sale['campaign_id'] ?? null,
                'corrected_campaign_date' => $sale['campaign_date'] ?? null,
            ]);

            return $this->remember($cacheKey, $this->identifiedOutcome($correctedRefId, 'corrected', $sale, $patientIc));
        }

        if (! $refCustomer) {
            Log::error('RefIdVerificationService: ref_id does not resolve to a sale and no unique replacement found, keeping ref_id', $mismatchContext + [
                'candidate_count' => count($candidates),
            ]);

            return $this->remember($cacheKey, $this->outcome($refId, 'not_found'));
        }

        Log::error('RefIdVerificationService: ref_id belongs to a different customer and no unique replacement found, dropping ref_id', $mismatchContext + [
            'candidate_count' => count($candidates),
            'candidate_ref_ids' => array_map(
                fn (array $sale) => strtoupper($labCode.$sale['blood_test_sales_id']),
                $candidates
            ),
        ]);

        return $this->remember($cacheKey, $this->outcome(null, 'unresolved'));
    }

    /**
     * No ref_id delivered: take the patient's single unused sale whose campaign
     * date is the collected date. Anything else leaves the ref_id empty.
     */
    protected function fillMissingRefId(array $icSales, string $labCode, string $patientIc, ?string $collectedDate, ?string $labNo, array $context): array
    {
        $candidates = $this->candidateSales($icSales, $labCode, $collectedDate, $labNo);

        if (count($candidates) !== 1) {
            Log::info('RefIdVerificationService: no ref_id delivered and no unique sale on the collected date, leaving ref_id empty', $context + [
                'candidate_count' => count($candidates),
            ]);

            return $this->outcome(null, 'skipped');
        }

        $sale = $candidates[0];
        $filledRefId = strtoupper($labCode.$sale['blood_test_sales_id']);

        // error level so the fill stays visible under production LOG_LEVEL=error
        Log::error('RefIdVerificationService: no ref_id delivered, filled from the patient sale on the collected date', $context + [
            'filled_ref_id' => $filledRefId,
            'customer_id' => $sale['customer_id'] ?? null,
            'campaign_id' => $sale['campaign_id'] ?? null,
            'campaign_date' => $sale['campaign_date'] ?? null,
        ]);

        return $this->identifiedOutcome($filledRefId, 'filled', $sale, $patientIc);
    }

    /**
     * Same person when the ODB customer's IC matches the patient IC, or (to
     * tolerate an IC typo on either side) when the ICs differ by at most
     * MAX_IC_TYPO_DISTANCE characters and either the names are near-identical
     * or the customer IC carries the delivered date of birth. A similar name
     * alone is not enough: common names collide across people.
     */
    protected function isSamePerson(array $customer, string $patientIc, ?string $patientName, ?string $patientDob = null): bool
    {
        $customerIc = $this->icNormalizer->normalize((string) ($customer['ic'] ?? ''));
        $normalizedPatientIc = $this->icNormalizer->normalize($patientIc);

        if ($customerIc === '') {
            return false;
        }

        if ($customerIc === $normalizedPatientIc) {
            return true;
        }

        if (levenshtein($customerIc, $normalizedPatientIc) > self::MAX_IC_TYPO_DISTANCE) {
            return false;
        }

        return $this->namesMatch($customer['customer_name'] ?? null, $patientName)
            || $this->icMatchesDob($customerIc, $patientDob);
    }

    protected function namesMatch(?string $customerName, ?string $patientName): bool
    {
        $customerName = $this->normalizeName($customerName);
        $name = $this->normalizeName($patientName);

        if ($customerName === '' || $name === '') {
            return false;
        }

        similar_text($customerName, $name, $percent);

        return $percent >= self::NAME_SIMILARITY_THRESHOLD;
    }

    /**
     * True when a 12-digit NRIC starts with the delivered date of birth (YYMMDD).
     */
    protected function icMatchesDob(string $ic, ?string $dob): bool
    {
        if (! $dob || ! preg_match('/^[0-9]{12}$/', $ic)) {
            return false;
        }

        try {
            return substr($ic, 0, 6) === Carbon::parse($dob)->format('ymd');
        } catch (Throwable $e) {
            return false;
        }
    }

    public function normalizeName(?string $name): string
    {
        $name = strtoupper((string) $name);
        $name = preg_replace('/[^A-Z ]/', ' ', $name);

        return trim(preg_replace('/\s+/', ' ', $name));
    }

    /**
     * The patient's sales whose campaign date (blood_test_calendar) is the same
     * day as the collected date and that are not already the ref_id of another
     * lab result. A sale from any other campaign is never substituted.
     */
    protected function candidateSales(array $icSales, string $labCode, ?string $collectedDate, ?string $labNo): array
    {
        if (empty($icSales) || ! $collectedDate) {
            return [];
        }

        $collectedDay = Carbon::parse($collectedDate)->toDateString();

        $sameCampaignDay = array_values(array_filter($icSales, function (array $sale) use ($collectedDay) {
            if (empty($sale['blood_test_sales_id']) || empty($sale['campaign_date']) || str_starts_with($sale['campaign_date'], '0000')) {
                return false;
            }

            return Carbon::parse($sale['campaign_date'])->toDateString() === $collectedDay;
        }));

        if (empty($sameCampaignDay)) {
            return [];
        }

        $refIds = array_map(fn (array $sale) => strtoupper($labCode.$sale['blood_test_sales_id']), $sameCampaignDay);

        $usedRefIds = TestResult::whereIn('ref_id', $refIds)
            ->when($labNo, fn ($query) => $query->where('lab_no', '!=', $labNo))
            ->pluck('ref_id')
            ->map(fn ($ref) => strtoupper($ref))
            ->all();

        return array_values(array_filter(
            $sameCampaignDay,
            fn (array $sale) => ! in_array(strtoupper($labCode.$sale['blood_test_sales_id']), $usedRefIds, true)
        ));
    }

    protected function outcome(?string $refId, string $status): array
    {
        return [
            'ref_id' => $refId,
            'status' => $status,
            'customer_name' => null,
            'customer_ic' => null,
            'ic_conflict' => false,
        ];
    }

    /**
     * Outcome carrying the ODB customer behind the sale (name and IC registered
     * at the sale), with ic_conflict when that IC differs from the delivered IC.
     */
    protected function identifiedOutcome(string $refId, string $status, array $customer, string $patientIc): array
    {
        $customerIc = trim((string) ($customer['ic'] ?? ''));
        $customerName = trim((string) ($customer['customer_name'] ?? ''));

        return [
            'ref_id' => $refId,
            'status' => $status,
            'customer_name' => $customerName !== '' ? $customerName : null,
            'customer_ic' => $customerIc !== '' ? $customerIc : null,
            'ic_conflict' => $customerIc !== ''
                && $this->icNormalizer->normalize($customerIc) !== $this->icNormalizer->normalize($patientIc),
        ];
    }

    protected function remember(string $cacheKey, array $outcome): array
    {
        Cache::put($cacheKey, $outcome, self::CACHE_TTL_SECONDS);

        return $outcome;
    }
}

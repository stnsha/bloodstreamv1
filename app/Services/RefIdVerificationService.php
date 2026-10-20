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
 * sale by IC and substitutes it.
 *
 * Outcomes (the 'status' key of verify()):
 *  - verified:    ref_id owner matches the patient (same IC, or IC typo plus same name); ref_id kept
 *  - corrected:   mismatch; exactly one unused sale found for the patient IC
 *                 whose campaign date equals the collected date; ref_id replaced
 *  - unresolved:  mismatch; no unique replacement; ref_id dropped (null) so it
 *                 cannot link the result to the wrong customer
 *  - not_found:   ref_id does not resolve to a sale and no unique replacement; ref_id kept
 *  - skipped:     nothing to verify (no ref_id, no usable patient IC, no lab code)
 *  - unavailable: ODB lookup failed; ref_id kept (fail-open)
 */
class RefIdVerificationService
{
    /**
     * Minimum similar_text() percentage for two names to count as the same person.
     */
    const NAME_SIMILARITY_THRESHOLD = 85.0;

    /**
     * Maximum IC edit distance still treated as a typo of the same IC (only
     * accepted together with a matching name).
     */
    const MAX_IC_TYPO_DISTANCE = 2;

    /**
     * Definitive outcomes are cached so incremental Innoquest deliveries of the
     * same lab_no do not repeat the ODB round trip.
     */
    const CACHE_TTL_SECONDS = 21600;

    protected OctopusApiService $octopusApi;

    protected IcNormalizerService $icNormalizer;

    public function __construct(OctopusApiService $octopusApi, IcNormalizerService $icNormalizer)
    {
        $this->octopusApi = $octopusApi;
        $this->icNormalizer = $icNormalizer;
    }

    /**
     * @return array{ref_id: string|null, status: string}
     */
    public function verify(
        ?string $refId,
        ?string $labCode,
        ?string $patientIc,
        ?string $patientName,
        ?string $collectedDate,
        ?string $labNo
    ): array {
        if (! $refId || ! $labCode || ! $patientIc || str_starts_with($patientIc, 'N/A_')) {
            return ['ref_id' => $refId, 'status' => 'skipped'];
        }

        $cacheKey = 'ref_id_verification_'.md5(implode('|', [$labNo, $refId, $patientIc]));
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
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

            return ['ref_id' => $refId, 'status' => 'unavailable'];
        }

        if ($data === null) {
            Log::error('RefIdVerificationService: ODB lookup returned no data, keeping ref_id unverified', $context);

            return ['ref_id' => $refId, 'status' => 'unavailable'];
        }

        $refCustomer = $data['ref_customer'];

        if ($refCustomer && $this->isSamePerson($refCustomer, $patientIc, $patientName)) {
            Log::info('RefIdVerificationService: ref_id verified', $context + [
                'customer_id' => $refCustomer['customer_id'] ?? null,
            ]);

            return $this->remember($cacheKey, ['ref_id' => $refId, 'status' => 'verified']);
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

            return $this->remember($cacheKey, ['ref_id' => $correctedRefId, 'status' => 'corrected']);
        }

        if (! $refCustomer) {
            Log::error('RefIdVerificationService: ref_id does not resolve to a sale and no unique replacement found, keeping ref_id', $mismatchContext + [
                'candidate_count' => count($candidates),
            ]);

            return $this->remember($cacheKey, ['ref_id' => $refId, 'status' => 'not_found']);
        }

        Log::error('RefIdVerificationService: ref_id belongs to a different customer and no unique replacement found, dropping ref_id', $mismatchContext + [
            'candidate_count' => count($candidates),
            'candidate_ref_ids' => array_map(
                fn (array $sale) => strtoupper($labCode.$sale['blood_test_sales_id']),
                $candidates
            ),
        ]);

        return $this->remember($cacheKey, ['ref_id' => null, 'status' => 'unresolved']);
    }

    /**
     * Same person when the ODB customer's IC matches the patient IC, or (to
     * tolerate an IC typo on either side) when the ICs differ by at most
     * MAX_IC_TYPO_DISTANCE characters and the names are near-identical. A
     * similar name alone is not enough: common names collide across people.
     */
    protected function isSamePerson(array $customer, string $patientIc, ?string $patientName): bool
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

        $customerName = $this->normalizeName($customer['customer_name'] ?? null);
        $name = $this->normalizeName($patientName);

        if ($customerName === '' || $name === '') {
            return false;
        }

        similar_text($customerName, $name, $percent);

        return $percent >= self::NAME_SIMILARITY_THRESHOLD;
    }

    protected function normalizeName(?string $name): string
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

    protected function remember(string $cacheKey, array $outcome): array
    {
        Cache::put($cacheKey, $outcome, self::CACHE_TTL_SECONDS);

        return $outcome;
    }
}

<?php

namespace App\Services\ARR;

use App\Services\OctopusApiService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class ARREligibilityService
{
    /**
     * On or after this date (server time, 00:00), consult-call outlet eligibility
     * is checked against the ARR-active outlet clusters (ODB outlet_cluster,
     * arr_active = 1) instead of the regional outlet list.
     */
    const ARR_FROM = '2026-11-23';

    protected OctopusApiService $octopusApi;

    public function __construct(OctopusApiService $octopusApi)
    {
        $this->octopusApi = $octopusApi;
    }

    /**
     * True once the ARR outlet-cluster eligibility is in effect.
     */
    public function isActive(): bool
    {
        return now()->gte(Carbon::parse(self::ARR_FROM)->startOfDay());
    }

    /**
     * Resolve the eligible customer for a lab ref_id and whether it is a cluster
     * (ARR) patient. From ARR_FROM one combined ODB lookup
     * (/eligibleConsultCallByOutletCluster.php) decides both: an outlet in an
     * ARR-active clinic cluster returns is_cluster = true (ARR flow), any other
     * eligible outlet is_cluster = false (consult call flow). Before ARR_FROM
     * the regional lookup applies and is_cluster is always false.
     * Returns the ODB customer array (customer_id, outlet_id, ...) plus
     * is_cluster, or null when the outlet is not eligible or the customer is
     * not found.
     *
     * @throws Exception when the ODB lookup fails
     */
    public function eligibleCustomerByRefId(string $refId, ?string $labCode = null): ?array
    {
        $arrActive = $this->isActive();

        Log::info('ARREligibilityService: resolving eligible customer by ref_id', [
            'ref_id' => $refId,
            'lab_code' => $labCode,
            'arr_active' => $arrActive,
        ]);

        $customer = $arrActive
            ? $this->octopusApi->eligibleConsultCallByOutletCluster($refId, $labCode)
            : $this->octopusApi->eligibleConsultCallByOutlet($refId, $labCode);

        if ($customer === null) {
            return null;
        }

        $customer['is_cluster'] = $arrActive && ! empty($customer['is_cluster']);

        Log::info('ARREligibilityService: eligible customer resolved', [
            'ref_id' => $refId,
            'customer_id' => $customer['customer_id'] ?? null,
            'outlet_id' => $customer['outlet_id'] ?? null,
            'is_cluster' => $customer['is_cluster'],
        ]);

        return $customer;
    }
}

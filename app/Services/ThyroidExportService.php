<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ThyroidExportService
{
    private const TSH      = 101;
    private const FT4      = 102;
    private const FT3      = 103;
    private const LAB_ID   = 2;
    private const LAB_CODE = 'INN';

    private OctopusApiService $octopus;

    public function __construct(OctopusApiService $octopus)
    {
        $this->octopus = $octopus;
    }

    /**
     * Yield one CSV row per test result for the thyroid panel export.
     * Uses a two-pass approach: pre-builds ODB lookup maps, then streams the main query.
     *
     * @param string   $dateFrom Y-m-d start date (inclusive)
     * @param string   $dateTo   Y-m-d end date (inclusive)
     * @param int|null $limit    Optional row cap (for sample runs)
     * @return Generator
     */
    public function rowGenerator(string $dateFrom, string $dateTo, ?int $limit = null, ?callable $onProgress = null): Generator
    {
        Log::channel('thyroid-export')->info('ThyroidExportService: starting row generator', [
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'limit'     => $limit,
        ]);

        // For limited runs: fetch the exact target rows first so we build the
        // customer map only for those ref_ids (avoids ORDER BY / DISTINCT mismatch).
        // For full runs: collect all distinct ref_ids via cursor, then stream.
        if ($limit !== null) {
            $targetRows = $this->buildMainQuery($dateFrom, $dateTo)->limit($limit)->get()->all();
            $refIds     = array_unique(array_column($targetRows, 'ref_id'));
        } else {
            $targetRows = null;
            $refIds     = $this->collectUniqueRefIds($dateFrom, $dateTo);
        }

        Log::channel('thyroid-export')->info('ThyroidExportService: ref_ids collected', [
            'count' => count($refIds),
        ]);

        $customerMap = $this->buildCustomerMap($refIds);

        Log::channel('thyroid-export')->info('ThyroidExportService: customer map built', [
            'matched' => count(array_filter($customerMap)),
        ]);

        $outletIds = [];
        foreach ($customerMap as $data) {
            if ($data !== null && ! empty($data['outlet_id'])) {
                $outletIds[(int) $data['outlet_id']] = true;
            }
        }
        $outletMap = $this->buildOutletMap(array_keys($outletIds));

        Log::channel('thyroid-export')->info('ThyroidExportService: outlet map built', [
            'outlets' => count($outletMap),
        ]);

        $source = ($targetRows !== null) ? $targetRows : $this->buildMainQuery($dateFrom, $dateTo)->cursor();

        foreach ($source as $row) {
            $row  = (object) $row;
            $cust = $customerMap[$row->ref_id] ?? null;

            $outlet = ($cust !== null && ! empty($cust['outlet_id']))
                ? ($outletMap[(int) $cust['outlet_id']] ?? null)
                : null;

            $age    = $this->resolveAge($row->patient_dob ?? null, $cust, $row->collected_date ?? null);
            $gender = (! empty($row->patient_gender)) ? $row->patient_gender : ($cust['gender'] ?? null);
            $race   = ($cust !== null) ? ($cust['race'] ?? 'Unknown') : null;

            yield [
                $row->lab_no,
                $row->ref_id,
                $row->collected_date,
                $age,
                $gender,
                $race,
                $outlet['regional']  ?? null,
                $outlet['comp_name'] ?? null,
                $row->tsh_value      ?? null,
                $row->tsh_ref_range  ?? null,
                $row->ft4_value      ?? null,
                $row->ft4_ref_range  ?? null,
                $row->ft3_value      ?? null,
                $row->ft3_ref_range  ?? null,
            ];

            if ($onProgress !== null) {
                ($onProgress)();
            }
        }
    }

    /**
     * Count matching rows for --dry-run.
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @return int
     */
    public function countMatchingRows(string $dateFrom, string $dateTo): int
    {
        return (int) DB::table('test_results as tr')
            ->join('doctors as d', function ($join) {
                $join->on('d.id', '=', 'tr.doctor_id')
                     ->where('d.lab_id', '=', self::LAB_ID);
            })
            ->where('tr.is_completed', 1)
            ->whereNotNull('tr.ref_id')
            ->whereNull('tr.deleted_at')
            ->whereBetween('tr.collected_date', [$dateFrom, $dateTo])
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('test_result_items')
                  ->whereColumn('test_result_id', 'tr.id')
                  ->whereIn('panel_panel_item_id', [self::TSH, self::FT4, self::FT3])
                  ->whereNull('deleted_at');
            })
            ->count();
    }

    /**
     * Collect all distinct ref_ids matching the export filters (full export path).
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @return array<string>
     */
    private function collectUniqueRefIds(string $dateFrom, string $dateTo): array
    {
        $refIds = [];

        $cursor = DB::table('test_results as tr')
            ->join('doctors as d', function ($join) {
                $join->on('d.id', '=', 'tr.doctor_id')
                     ->where('d.lab_id', '=', self::LAB_ID);
            })
            ->where('tr.is_completed', 1)
            ->whereNotNull('tr.ref_id')
            ->whereNull('tr.deleted_at')
            ->whereBetween('tr.collected_date', [$dateFrom, $dateTo])
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('test_result_items')
                  ->whereColumn('test_result_id', 'tr.id')
                  ->whereIn('panel_panel_item_id', [self::TSH, self::FT4, self::FT3])
                  ->whereNull('deleted_at');
            })
            ->select('tr.ref_id')
            ->distinct()
            ->cursor();

        foreach ($cursor as $row) {
            $refIds[] = $row->ref_id;
        }

        return $refIds;
    }

    /**
     * Build customer data map from ODB API.
     * Returns [ref_id => ['birth_date', 'gender', 'race', 'outlet_id']] or null on lookup failure.
     *
     * @param array<string> $refIds
     * @return array<string, array|null>
     */
    private function buildCustomerMap(array $refIds): array
    {
        $map = [];

        foreach ($refIds as $refId) {
            try {
                $customer = $this->octopus->customerByRefId($refId, self::LAB_CODE);

                if ($customer === null) {
                    $map[$refId] = null;
                    continue;
                }

                $map[$refId] = [
                    'birth_date' => $customer['birth_date'] ?? null,
                    'gender'     => $customer['gender']     ?? null,
                    'race'       => $customer['race']       ?? null,
                    'outlet_id'  => $customer['outlet_id']  ?? null,
                ];

            } catch (Exception $e) {
                Log::channel('thyroid-export')->warning('ThyroidExportService: customer lookup failed', [
                    'ref_id' => $refId,
                    'error'  => $e->getMessage(),
                ]);

                $map[$refId] = null;
            }
        }

        return $map;
    }

    /**
     * Build outlet data map from ODB API for the given unique outlet IDs.
     * Returns [outlet_id => ['comp_name', 'regional']]
     *
     * @param array<int> $outletIds
     * @return array<int, array|null>
     */
    private function buildOutletMap(array $outletIds): array
    {
        $map = [];

        foreach ($outletIds as $outletId) {
            try {
                $outlet = $this->octopus->outletById($outletId);

                $map[$outletId] = ($outlet !== null) ? [
                    'comp_name' => $outlet['comp_name'] ?? null,
                    'regional'  => $outlet['regional']  ?? null,
                ] : null;

            } catch (Exception $e) {
                Log::channel('thyroid-export')->warning('ThyroidExportService: outlet lookup failed', [
                    'outlet_id' => $outletId,
                    'error'     => $e->getMessage(),
                ]);

                $map[$outletId] = null;
            }
        }

        return $map;
    }

    /**
     * Build the base query for thyroid test result rows with pivoted thyroid values.
     * Call ->cursor() for streaming or ->limit(N)->get() for sample runs.
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @return \Illuminate\Database\Query\Builder
     */
    private function buildMainQuery(string $dateFrom, string $dateTo)
    {
        return DB::table('test_results as tr')
            ->join('doctors as d', function ($join) {
                $join->on('d.id', '=', 'tr.doctor_id')
                     ->where('d.lab_id', '=', self::LAB_ID);
            })
            ->leftJoin('patients as p', function ($join) {
                $join->on('p.id', '=', 'tr.patient_id')
                     ->whereNull('p.deleted_at');
            })
            ->leftJoin('test_result_items as tsh_i', function ($join) {
                $join->on('tsh_i.test_result_id', '=', 'tr.id')
                     ->where('tsh_i.panel_panel_item_id', '=', self::TSH)
                     ->whereNull('tsh_i.deleted_at');
            })
            ->leftJoin('reference_ranges as tsh_rr', 'tsh_rr.id', '=', 'tsh_i.reference_range_id')
            ->leftJoin('test_result_items as ft4_i', function ($join) {
                $join->on('ft4_i.test_result_id', '=', 'tr.id')
                     ->where('ft4_i.panel_panel_item_id', '=', self::FT4)
                     ->whereNull('ft4_i.deleted_at');
            })
            ->leftJoin('reference_ranges as ft4_rr', 'ft4_rr.id', '=', 'ft4_i.reference_range_id')
            ->leftJoin('test_result_items as ft3_i', function ($join) {
                $join->on('ft3_i.test_result_id', '=', 'tr.id')
                     ->where('ft3_i.panel_panel_item_id', '=', self::FT3)
                     ->whereNull('ft3_i.deleted_at');
            })
            ->leftJoin('reference_ranges as ft3_rr', 'ft3_rr.id', '=', 'ft3_i.reference_range_id')
            ->where('tr.is_completed', 1)
            ->whereNotNull('tr.ref_id')
            ->whereNull('tr.deleted_at')
            ->whereBetween('tr.collected_date', [$dateFrom, $dateTo])
            ->where(function ($q) {
                $q->whereNotNull('tsh_i.id')
                  ->orWhereNotNull('ft4_i.id')
                  ->orWhereNotNull('ft3_i.id');
            })
            ->orderBy('tr.collected_date')
            ->select([
                'tr.lab_no',
                'tr.ref_id',
                'tr.collected_date',
                'p.dob    as patient_dob',
                'p.gender as patient_gender',
                'tsh_i.value   as tsh_value',
                'tsh_rr.value  as tsh_ref_range',
                'ft4_i.value   as ft4_value',
                'ft4_rr.value  as ft4_ref_range',
                'ft3_i.value   as ft3_value',
                'ft3_rr.value  as ft3_ref_range',
            ]);
    }

    /**
     * Resolve patient age at the time of the collected_date.
     * Prefers local patients.dob; falls back to ODB birth_date.
     *
     * @param string|null $patientDob
     * @param array|null  $custData
     * @param string|null $collectedDate
     * @return int|null
     */
    private function resolveAge(?string $patientDob, ?array $custData, ?string $collectedDate): ?int
    {
        if (empty($collectedDate)) {
            return null;
        }

        $atDate = Carbon::parse($collectedDate);

        if (! empty($patientDob)) {
            return Carbon::parse($patientDob)->diffInYears($atDate);
        }

        if ($custData !== null && ! empty($custData['birth_date'])) {
            return Carbon::parse($custData['birth_date'])->diffInYears($atDate);
        }

        return null;
    }
}

<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DynamicExportService
{
    private OctopusApiService $octopus;

    public function __construct(OctopusApiService $octopus)
    {
        $this->octopus = $octopus;
    }

    /**
     * Return all active master_panel_items ordered by name.
     *
     * @return array [['master_panel_item_id', 'name', 'unit'], ...]
     */
    public function getMasterPanelItems(): array
    {
        Log::info('DynamicExportService: getMasterPanelItems');

        $rows = DB::table('master_panel_items')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->select(['id as master_panel_item_id', 'name', 'unit'])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'master_panel_item_id' => $row->master_panel_item_id,
                'name'                 => $row->name,
                'unit'                 => $row->unit,
            ];
        }

        Log::info('DynamicExportService: getMasterPanelItems complete', [
            'count' => count($result),
        ]);

        return $result;
    }

    /**
     * Resolve master_panel_item_ids to all matching panel_panel_item_ids across all labs.
     *
     * @param  array $masterPanelItemIds
     * @return array panel_panel_item_ids
     */
    public function resolvePpiIds(array $masterPanelItemIds): array
    {
        $rows = DB::table('panel_panel_items as ppi')
            ->join('panel_items as pi', function ($join) {
                $join->on('pi.id', '=', 'ppi.panel_item_id')
                     ->whereNull('pi.deleted_at');
            })
            ->whereIn('pi.master_panel_item_id', $masterPanelItemIds)
            ->pluck('ppi.id')
            ->toArray();

        return array_map('intval', $rows);
    }

    /**
     * Count distinct test_results matching the given filters.
     *
     * @param  string $dateFrom
     * @param  string $dateTo
     * @param  array  $masterPanelItemIds
     * @return int
     */
    public function countResults(string $dateFrom, string $dateTo, array $masterPanelItemIds): int
    {
        $ppiIds = $this->resolvePpiIds($masterPanelItemIds);

        if (empty($ppiIds)) {
            return 0;
        }

        Log::info('DynamicExportService: countResults', [
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
            'mpi_count'  => count($masterPanelItemIds),
            'ppi_count'  => count($ppiIds),
        ]);

        $count = (int) DB::table('test_results as tr')
            ->join('doctors as d', 'd.id', '=', 'tr.doctor_id')
            ->where('tr.is_completed', 1)
            ->whereNotNull('tr.ref_id')
            ->whereNull('tr.deleted_at')
            ->whereBetween('tr.collected_date', [$dateFrom, $dateTo])
            ->whereExists(function ($q) use ($ppiIds) {
                $q->select(DB::raw(1))
                  ->from('test_result_items')
                  ->whereColumn('test_result_id', 'tr.id')
                  ->whereIn('panel_panel_item_id', $ppiIds)
                  ->whereNull('deleted_at');
            })
            ->count();

        Log::info('DynamicExportService: countResults complete', ['count' => $count]);

        return $count;
    }

    /**
     * Generate a CSV export and return it as a base64-encoded string.
     *
     * @param  string $dateFrom
     * @param  string $dateTo
     * @param  array  $masterPanelItemIds
     * @param  array  $columns
     * @param  bool   $includeOctopus
     * @param  string $labCode
     * @return array  ['csv_base64' => string, 'row_count' => int, 'warnings' => array]
     */
    public function generateCsv(
        string $dateFrom,
        string $dateTo,
        array $masterPanelItemIds,
        array $columns,
        bool $includeOctopus,
        string $labCode = ''
    ): array {
        $ppiIds = $this->resolvePpiIds($masterPanelItemIds);

        if (empty($ppiIds)) {
            return ['csv_base64' => base64_encode(''), 'row_count' => 0, 'warnings' => ['No matching panel items found.']];
        }

        Log::info('DynamicExportService: generateCsv start', [
            'date_from'       => $dateFrom,
            'date_to'         => $dateTo,
            'mpi_count'       => count($masterPanelItemIds),
            'ppi_count'       => count($ppiIds),
            'columns'         => $columns,
            'include_octopus' => $includeOctopus,
        ]);

        $ppiMeta     = $this->resolvePpiMeta($ppiIds);
        $customerMap = [];
        $outletMap   = [];

        if ($includeOctopus) {
            Log::info('DynamicExportService: collecting ref_ids for Octopus lookup');
            $refIds      = $this->collectDistinctRefIds($dateFrom, $dateTo, $ppiIds);
            $customerMap = $this->buildCustomerMap($refIds, $labCode);

            $outletIds = [];
            foreach ($customerMap as $custData) {
                if ($custData !== null && !empty($custData['outlet_id'])) {
                    $outletIds[(int) $custData['outlet_id']] = true;
                }
            }
            $outletMap = $this->buildOutletMap(array_keys($outletIds));
        }

        $stream         = fopen('php://memory', 'w+');
        $rowCount       = 0;
        $headersWritten = false;
        $cursor         = $this->buildMainQuery($dateFrom, $dateTo, $ppiIds)->cursor();
        $buffer         = [];

        foreach ($cursor as $row) {
            $buffer[] = $row;

            if (count($buffer) >= 500) {
                $this->processBuffer(
                    $buffer, $ppiIds, $ppiMeta, $columns,
                    $customerMap, $outletMap, $includeOctopus,
                    $stream, $headersWritten, $rowCount
                );
                $buffer = [];
            }
        }

        if (!empty($buffer)) {
            $this->processBuffer(
                $buffer, $ppiIds, $ppiMeta, $columns,
                $customerMap, $outletMap, $includeOctopus,
                $stream, $headersWritten, $rowCount
            );
        }

        rewind($stream);
        $csvContent = stream_get_contents($stream);
        fclose($stream);

        Log::info('DynamicExportService: generateCsv complete', ['row_count' => $rowCount]);

        return [
            'csv_base64' => base64_encode($csvContent),
            'row_count'  => $rowCount,
            'warnings'   => $includeOctopus
                ? ['Customer name, NRIC, phone, race and regional data sourced from external API. Some records may have null values.']
                : [],
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolvePpiMeta(array $ppiIds): array
    {
        $rows = DB::table('panel_panel_items as ppi')
            ->join('panel_items as pi', 'pi.id', '=', 'ppi.panel_item_id')
            ->join('master_panel_items as mpi', 'mpi.id', '=', 'pi.master_panel_item_id')
            ->whereIn('ppi.id', $ppiIds)
            ->select(['ppi.id as ppi_id', 'mpi.name as panel_item_name', 'mpi.unit'])
            ->get();

        $meta = [];
        foreach ($rows as $row) {
            $meta[$row->ppi_id] = [
                'panel_item_name' => $row->panel_item_name,
                'unit'            => $row->unit,
            ];
        }

        return $meta;
    }

    private function collectDistinctRefIds(string $dateFrom, string $dateTo, array $ppiIds): array
    {
        $refIds = [];

        $cursor = DB::table('test_results as tr')
            ->join('doctors as d', 'd.id', '=', 'tr.doctor_id')
            ->where('tr.is_completed', 1)
            ->whereNotNull('tr.ref_id')
            ->whereNull('tr.deleted_at')
            ->whereBetween('tr.collected_date', [$dateFrom, $dateTo])
            ->whereExists(function ($q) use ($ppiIds) {
                $q->select(DB::raw(1))
                  ->from('test_result_items')
                  ->whereColumn('test_result_id', 'tr.id')
                  ->whereIn('panel_panel_item_id', $ppiIds)
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

    private function buildMainQuery(string $dateFrom, string $dateTo, array $ppiIds)
    {
        return DB::table('test_results as tr')
            ->join('doctors as d', 'd.id', '=', 'tr.doctor_id')
            ->leftJoin('patients as p', function ($join) {
                $join->on('p.id', '=', 'tr.patient_id')
                     ->whereNull('p.deleted_at');
            })
            ->where('tr.is_completed', 1)
            ->whereNotNull('tr.ref_id')
            ->whereNull('tr.deleted_at')
            ->whereBetween('tr.collected_date', [$dateFrom, $dateTo])
            ->whereExists(function ($q) use ($ppiIds) {
                $q->select(DB::raw(1))
                  ->from('test_result_items')
                  ->whereColumn('test_result_id', 'tr.id')
                  ->whereIn('panel_panel_item_id', $ppiIds)
                  ->whereNull('deleted_at');
            })
            ->orderBy('tr.collected_date')
            ->select([
                'tr.id',
                'tr.lab_no',
                'tr.ref_id',
                'tr.collected_date',
                'p.dob    as patient_dob',
                'p.gender as patient_gender',
                'd.code as outlet_code',
            ]);
    }

    private function fetchItemsForChunk(array $testResultIds, array $ppiIds): array
    {
        $rows = DB::table('test_result_items as trii')
            ->leftJoin('reference_ranges as rr', function ($join) {
                $join->on('rr.id', '=', 'trii.reference_range_id')
                     ->whereNull('rr.deleted_at');
            })
            ->whereIn('trii.test_result_id', $testResultIds)
            ->whereIn('trii.panel_panel_item_id', $ppiIds)
            ->whereNull('trii.deleted_at')
            ->select([
                'trii.test_result_id',
                'trii.panel_panel_item_id',
                'trii.value as result_value',
                'trii.flag',
                'rr.value   as ref_range',
            ])
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $items[$row->test_result_id][$row->panel_panel_item_id] = [
                'result_value' => $row->result_value,
                'flag'         => $row->flag,
                'ref_range'    => $row->ref_range,
            ];
        }

        return $items;
    }

    private function processBuffer(
        array $buffer,
        array $ppiIds,
        array $ppiMeta,
        array $columns,
        array $customerMap,
        array $outletMap,
        bool $includeOctopus,
        $stream,
        bool &$headersWritten,
        int &$rowCount
    ): void {
        $testResultIds = array_map(function ($r) { return $r->id; }, $buffer);
        $items         = $this->fetchItemsForChunk($testResultIds, $ppiIds);

        if (!$headersWritten) {
            fputcsv($stream, $this->buildHeaders($columns, $ppiIds, $ppiMeta));
            $headersWritten = true;
        }

        foreach ($buffer as $row) {
            $cust     = $customerMap[$row->ref_id] ?? null;
            $outletId = ($cust !== null && !empty($cust['outlet_id'])) ? (int) $cust['outlet_id'] : null;
            $outlet   = ($outletId !== null) ? ($outletMap[$outletId] ?? null) : null;

            $age          = $this->resolveAge($row->patient_dob ?? null, $cust, $row->collected_date ?? null);
            $gender       = $this->resolveGender($row->patient_gender ?? null, $cust);
            $race         = $includeOctopus ? ($cust['race']          ?? null) : null;
            $regional     = ($includeOctopus && $outlet !== null) ? ($outlet['regional'] ?? null) : null;
            $customerName = $includeOctopus ? ($cust['customer_name'] ?? null) : null;
            $nric         = $includeOctopus ? ($cust['ic']            ?? null) : null;
            $phone        = $includeOctopus ? ($cust['phone']         ?? null) : null;

            fputcsv($stream, $this->buildDataRow(
                $row, $columns, $ppiIds,
                $items[$row->id] ?? [],
                $age, $gender, $race, $regional, $customerName, $nric, $phone
            ));
            $rowCount++;
        }
    }

    private function buildHeaders(array $columns, array $ppiIds, array $ppiMeta): array
    {
        $columnLabels = [
            'customer_name'  => 'Customer Name',
            'nric'           => 'NRIC',
            'phone'          => 'Phone',
            'lab_no'         => 'Lab No',
            'ref_id'         => 'Ref ID',
            'age'            => 'Age',
            'gender'         => 'Gender',
            'race'           => 'Race',
            'outlet_code'    => 'Outlet Code',
            'collected_date' => 'Collected Date',
            'regional'       => 'Regional',
        ];

        $headers = [];
        foreach ($columnLabels as $key => $label) {
            if (in_array($key, $columns)) {
                $headers[] = $label;
            }
        }

        foreach ($ppiIds as $ppiId) {
            $meta  = $ppiMeta[$ppiId] ?? ['panel_item_name' => 'Unknown', 'unit' => null];
            $label = $meta['panel_item_name'];
            if (!empty($meta['unit'])) {
                $label .= ' (' . $meta['unit'] . ')';
            }
            $headers[] = $label . ' Value';
            $headers[] = $label . ' Flag';
            $headers[] = $label . ' Ref Range';
        }

        return $headers;
    }

    private function buildDataRow(
        object $row,
        array $columns,
        array $ppiIds,
        array $rowItems,
        ?int $age,
        ?string $gender,
        ?string $race,
        ?string $regional,
        ?string $customerName,
        ?string $nric,
        ?string $phone
    ): array {
        $data = [];

        if (in_array('customer_name', $columns))   $data[] = $customerName        ?? '';
        if (in_array('nric', $columns))            $data[] = $nric                ?? '';
        if (in_array('phone', $columns))           $data[] = $phone               ?? '';
        if (in_array('lab_no', $columns))          $data[] = $row->lab_no         ?? '';
        if (in_array('ref_id', $columns))          $data[] = $row->ref_id         ?? '';
        if (in_array('age', $columns))             $data[] = $age                 ?? '';
        if (in_array('gender', $columns))          $data[] = $gender              ?? '';
        if (in_array('race', $columns))            $data[] = $race                ?? '';
        if (in_array('outlet_code', $columns))     $data[] = $row->outlet_code    ?? '';
        if (in_array('collected_date', $columns))  $data[] = $row->collected_date ?? '';
        if (in_array('regional', $columns))        $data[] = $regional            ?? '';

        foreach ($ppiIds as $ppiId) {
            $item   = $rowItems[$ppiId] ?? null;
            $data[] = ($item !== null) ? ($item['result_value'] ?? '') : '';
            $data[] = ($item !== null) ? ($item['flag']         ?? '') : '';
            $data[] = ($item !== null) ? ($item['ref_range']    ?? '') : '';
        }

        return $data;
    }

    private function buildCustomerMap(array $refIds, string $labCode): array
    {
        $map = [];
        foreach ($refIds as $refId) {
            try {
                $customer    = $this->octopus->customerByRefId($refId, $labCode);
                $map[$refId] = $customer === null ? null : [
                    'birth_date'    => $customer['birth_date'] ?? null,
                    'gender'        => $customer['gender']     ?? null,
                    'race'          => $customer['race']       ?? null,
                    'outlet_id'     => $customer['outlet_id']  ?? null,
                    'customer_name' => $customer['customer_name']  ?? null,
                    'ic'            => $customer['ic']             ?? null,
                    'phone'         => $customer['customer_phone'] ?? null,
                ];
            } catch (Exception $e) {
                Log::warning('DynamicExportService: customer lookup failed', ['ref_id' => $refId, 'error' => $e->getMessage()]);
                $map[$refId] = null;
            }
        }
        return $map;
    }

    private function buildOutletMap(array $outletIds): array
    {
        $map = [];
        foreach ($outletIds as $outletId) {
            try {
                $outlet          = $this->octopus->outletById($outletId);
                $map[$outletId]  = ($outlet !== null) ? [
                    'comp_name' => $outlet['comp_name'] ?? null,
                    'regional'  => $outlet['regional']  ?? null,
                ] : null;
            } catch (Exception $e) {
                Log::warning('DynamicExportService: outlet lookup failed', ['outlet_id' => $outletId, 'error' => $e->getMessage()]);
                $map[$outletId] = null;
            }
        }
        return $map;
    }

    private function resolveAge(?string $dob, ?array $custData, ?string $collectedDate): ?int
    {
        if (empty($collectedDate)) return null;

        $atDate = Carbon::parse($collectedDate);

        if (!empty($dob)) {
            return Carbon::parse($dob)->diffInYears($atDate);
        }
        if ($custData !== null && !empty($custData['birth_date'])) {
            return Carbon::parse($custData['birth_date'])->diffInYears($atDate);
        }

        return null;
    }

    private function resolveGender(?string $localGender, ?array $custData): ?string
    {
        if (!empty($localGender)) return $localGender;
        if ($custData !== null && !empty($custData['gender'])) return $custData['gender'];
        return null;
    }
}

<?php

namespace App\Imports\Innoquest;

use App\Imports\BaseCodeMappingImport;
use App\Models\MasterPanel;
use App\Models\Panel;

class PanelSequenceImport extends BaseCodeMappingImport
{
    protected function processRow(array $row): ?array
    {
        // Skip rows with missing essential data
        if (!$this->hasEssentialData($row)) {
            return null;
        }

        return [
            'code' => $this->trimOrNull($row['code']),
            'name' => $this->trimOrNull($row['name']),
            'report_order_alpro' => $this->trimOrNull($row['report_order_alpro']),
        ];
    }

    /**
     * Check if row has essential data for Panel Sequence import
     */
    protected function hasEssentialData(array $row): bool
    {
        // First check if the row is completely empty
        if ($this->isEmptyRow($row)) {
            return false;
        }

        // Panel Sequence import requires code and report_order_alpro
        $code = $this->trimOrNull($row['code'] ?? null);
        $reportOrder = $this->trimOrNull($row['report_order_alpro'] ?? null);

        return !empty($code) && !empty($reportOrder);
    }

    protected function getSkipReason(array $row): string
    {
        $code = $this->trimOrNull($row['code'] ?? null);
        $reportOrder = $this->trimOrNull($row['report_order_alpro'] ?? null);

        if (empty($code)) {
            return 'Missing panel code';
        }
        if (empty($reportOrder)) {
            return 'Missing report order value';
        }

        return 'Failed validation or processing requirements';
    }

    protected function store(array $processedData): void
    {
        foreach ($processedData as $data) {
            $masterPanel = MasterPanel::firstOrCreate(
                ['name' => $data['name']]
            );

            $this->trackDatabaseOperation('create', $masterPanel->wasRecentlyCreated);

            $panel = Panel::updateOrCreate(
                [
                    'lab_id' => $this->labId,
                    'master_panel_id' => $masterPanel->id,
                ],
                [
                    'name' => $data['name'],
                    'code' => $data['code'],
                    'sequence' => $data['report_order_alpro'],
                ]
            );

            $this->trackDatabaseOperation($panel->wasRecentlyCreated ? 'create' : 'update', $panel->wasRecentlyCreated);
        }
    }
}
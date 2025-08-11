<?php

namespace App\Imports;

use App\Models\ResultLibrary;

class BillCodeImport extends BaseCodeMappingImport
{
    protected function processRow(array $row): ?array
    {
        // Skip rows with missing essential data
        if (!$this->hasEssentialData($row)) {
            return null;
        }

        return [
            'billing_code' => $this->trimOrNull($row['billing_code']),
        ];
    }

    /**
     * Check if row has essential data for Bill Code import
     */
    protected function hasEssentialData(array $row): bool
    {
        // First check if the row is completely empty
        if ($this->isEmptyRow($row)) {
            return false;
        }

        // Bill Code import requires billing code
        $billingCode = $this->trimOrNull($row['billing_code'] ?? null);
        
        return !empty($billingCode);
    }

    protected function store(array $processedData): void
    {
        foreach ($processedData as $data) {
            $resultLibrary = ResultLibrary::firstOrCreate(
                [
                    'code' => $data['billing_code'],
                ],
                [
                    'type' => 'bill_code',
                    'value' => $data['billing_code'],
                    'description' => 'Innoquest billing code',
                ]
            );
            $this->trackDatabaseOperation('create', $resultLibrary->wasRecentlyCreated);
        }
    }

    public function rules(): array
    {
        return [
            '*.billing_code' => 'nullable|string',
        ];
    }
}

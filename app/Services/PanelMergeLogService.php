<?php

namespace App\Services;

use App\Models\PanelMergeDetail;
use App\Models\PanelMergeLog;

class PanelMergeLogService
{
    protected ?PanelMergeLog $currentLog = null;

    protected bool $isDryRun = false;

    /**
     * Set the current log to record details to.
     */
    public function setCurrentLog(?PanelMergeLog $log): self
    {
        $this->currentLog = $log;

        return $this;
    }

    /**
     * Set dry run mode.
     */
    public function setDryRun(bool $isDryRun): self
    {
        $this->isDryRun = $isDryRun;

        return $this;
    }

    /**
     * Get the current log.
     */
    public function getCurrentLog(): ?PanelMergeLog
    {
        return $this->currentLog;
    }

    /**
     * Check if logging is active.
     */
    public function isLogging(): bool
    {
        return $this->currentLog !== null && !$this->isDryRun;
    }

    /**
     * Log a created entity.
     */
    public function logCreated(
        string $entityType,
        int $entityId,
        ?string $entityName = null,
        ?string $entityUnit = null,
        ?string $description = null
    ): ?PanelMergeDetail {
        return $this->logDetail('created', $entityType, $entityId, $entityName, $entityUnit, null, null, null, null, $description);
    }

    /**
     * Log a deleted entity.
     */
    public function logDeleted(
        string $entityType,
        int $entityId,
        ?string $entityName = null,
        ?string $entityUnit = null,
        ?string $description = null
    ): ?PanelMergeDetail {
        return $this->logDetail('deleted', $entityType, $entityId, $entityName, $entityUnit, null, null, null, null, $description);
    }

    /**
     * Log an updated entity.
     */
    public function logUpdated(
        string $entityType,
        int $entityId,
        ?string $entityName = null,
        ?string $entityUnit = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null
    ): ?PanelMergeDetail {
        return $this->logDetail('updated', $entityType, $entityId, $entityName, $entityUnit, null, null, $oldValues, $newValues, $description);
    }

    /**
     * Log a merged entity.
     */
    public function logMerged(
        string $entityType,
        int $entityId,
        ?string $entityName,
        int $targetId,
        ?string $targetName,
        ?string $description = null
    ): ?PanelMergeDetail {
        return $this->logDetail('merged', $entityType, $entityId, $entityName, null, $targetId, $targetName, null, null, $description);
    }

    /**
     * Log a repointed entity.
     */
    public function logRepointed(
        string $entityType,
        int $entityId,
        ?string $entityName,
        int $oldReferenceId,
        int $newReferenceId,
        ?string $description = null
    ): ?PanelMergeDetail {
        return $this->logDetail(
            'repointed',
            $entityType,
            $entityId,
            $entityName,
            null,
            $newReferenceId,
            null,
            ['old_reference' => $oldReferenceId],
            ['new_reference' => $newReferenceId],
            $description
        );
    }

    /**
     * Log a detail record.
     */
    protected function logDetail(
        string $action,
        string $entityType,
        int $entityId,
        ?string $entityName,
        ?string $entityUnit,
        ?int $targetId,
        ?string $targetName,
        ?array $oldValues,
        ?array $newValues,
        ?string $description
    ): ?PanelMergeDetail {
        if (!$this->isLogging()) {
            return null;
        }

        return PanelMergeDetail::create([
            'panel_merge_log_id' => $this->currentLog->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_name' => $entityName,
            'entity_unit' => $entityUnit,
            'target_id' => $targetId,
            'target_name' => $targetName,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description,
        ]);
    }
}

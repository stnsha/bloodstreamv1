<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PanelMergeDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'panel_merge_log_id',
        'action',
        'entity_type',
        'entity_id',
        'entity_name',
        'entity_unit',
        'target_id',
        'target_name',
        'old_values',
        'new_values',
        'description',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the parent log.
     */
    public function log(): BelongsTo
    {
        return $this->belongsTo(PanelMergeLog::class, 'panel_merge_log_id');
    }

    /**
     * Get action badge class.
     */
    public function getActionBadgeClassAttribute(): string
    {
        return match ($this->action) {
            'created' => 'bg-green-100 text-green-800',
            'updated' => 'bg-blue-100 text-blue-800',
            'deleted' => 'bg-red-100 text-red-800',
            'merged' => 'bg-purple-100 text-purple-800',
            'repointed' => 'bg-yellow-100 text-yellow-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get a human-readable summary.
     */
    public function getSummaryAttribute(): string
    {
        $unit = $this->entity_unit ? " ({$this->entity_unit})" : '';
        $oldReference = $this->old_values['old_reference'] ?? '?';
        $defaultDescription = $this->description ?? "{$this->action} {$this->entity_type} #{$this->entity_id}";

        return match ($this->action) {
            'created' => "Created {$this->entity_type} #{$this->entity_id}: \"{$this->entity_name}\"{$unit}",
            'deleted' => "Deleted {$this->entity_type} #{$this->entity_id}: \"{$this->entity_name}\"{$unit}",
            'updated' => "Updated {$this->entity_type} #{$this->entity_id}: \"{$this->entity_name}\"{$unit}",
            'merged' => "Merged {$this->entity_type} #{$this->entity_id} into #{$this->target_id}: \"{$this->target_name}\"",
            'repointed' => "Repointed {$this->entity_type} #{$this->entity_id} from #{$oldReference} to #{$this->target_id}",
            default => $defaultDescription,
        };
    }
}

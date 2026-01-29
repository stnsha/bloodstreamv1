<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PanelMergeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'command',
        'status',
        'is_dry_run',
        'options',
        'stats',
        'output',
        'error',
        'started_at',
        'completed_at',
        'user_id',
    ];

    protected $casts = [
        'is_dry_run' => 'boolean',
        'options' => 'array',
        'stats' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the command display name.
     */
    public function getCommandDisplayNameAttribute(): string
    {
        $names = [
            'panel:merge-duplicates' => 'Merge Duplicate Master Panel Data',
            'panel:fix-mismatched-references' => 'Fix Mismatched PanelItem References',
            'panel:create-missing-master-items' => 'Create Missing MasterPanelItems',
        ];

        return $names[$this->command] ?? $this->command;
    }

    /**
     * Get status badge class.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'bg-yellow-100 text-yellow-800',
            'running' => 'bg-blue-100 text-blue-800',
            'completed' => 'bg-green-100 text-green-800',
            'failed' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get duration in human-readable format.
     */
    public function getDurationAttribute(): ?string
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        $seconds = $this->completed_at->diffInSeconds($this->started_at);

        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return "{$minutes}m {$remainingSeconds}s";
    }
}

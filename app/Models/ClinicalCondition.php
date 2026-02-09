<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ClinicalCondition extends Model
{
    protected $table = 'clinical_conditions';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'description',
        'evaluator',
        'risk_tier',
        'criteria_count',
        'is_active',
    ];

    protected $casts = [
        'id' => 'integer',
        'risk_tier' => 'integer',
        'criteria_count' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Scope for active conditions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get all active conditions keyed by ID with caching
     */
    public static function getAllCached(): array
    {
        return Cache::remember('clinical_conditions_all', 3600, function () {
            return self::active()->get()->keyBy('id')->toArray();
        });
    }

    /**
     * Get a single condition by ID
     */
    public static function getCondition(int $id): ?array
    {
        $all = self::getAllCached();

        return $all[$id] ?? null;
    }

    /**
     * Get all active conditions as array
     */
    public static function getAll(): array
    {
        return self::getAllCached();
    }

    /**
     * Get all active condition IDs
     */
    public static function getAllIds(): array
    {
        return array_keys(self::getAllCached());
    }

    /**
     * Get condition IDs sorted by priority (highest criteria_count first)
     */
    public static function getIdsSortedByPriority(): array
    {
        return Cache::remember('clinical_conditions_sorted', 3600, function () {
            return self::active()
                ->orderByDesc('criteria_count')
                ->orderBy('id')
                ->pluck('id')
                ->toArray();
        });
    }

    /**
     * Clear all clinical condition caches
     */
    public static function clearCache(): void
    {
        Cache::forget('clinical_conditions_all');
        Cache::forget('clinical_conditions_sorted');
    }
}

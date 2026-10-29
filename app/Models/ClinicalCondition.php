<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ClinicalCondition extends Model
{
    protected $table = 'clinical_conditions';

    public $incrementing = false;

    /**
     * cluster_scope values: which consult-call flow evaluates the condition.
     */
    const SCOPE_CONSULT_CALL = 1;

    const SCOPE_CLUSTER = 2;

    const SCOPE_BOTH = 3;

    protected $fillable = [
        'id',
        'description',
        'type',
        'cluster_scope',
        'evaluator',
        'risk_tier',
        'criteria_count',
        'is_active',
        'active_from',
    ];

    protected $casts = [
        'id' => 'integer',
        'cluster_scope' => 'integer',
        'risk_tier' => 'integer',
        'criteria_count' => 'integer',
        'is_active' => 'boolean',
        'active_from' => 'date:Y-m-d',
    ];

    /**
     * Scope for active conditions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to the conditions evaluated by one flow: cluster (ARR) patients get
     * SCOPE_CLUSTER and SCOPE_BOTH, consult-call patients SCOPE_CONSULT_CALL and SCOPE_BOTH.
     */
    public function scopeForFlow($query, bool $isCluster)
    {
        return $query->whereIn('cluster_scope', [
            $isCluster ? self::SCOPE_CLUSTER : self::SCOPE_CONSULT_CALL,
            self::SCOPE_BOTH,
        ]);
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
     * Get condition IDs sorted by priority (highest risk_tier first, then highest criteria_count).
     * Excludes the healthy condition (criteria_count = 0) so it is never
     * passed into the evaluator loop. Limited to the conditions of one flow
     * (consult call by default, cluster when $isCluster is true).
     */
    public static function getIdsSortedByPriority(bool $isCluster = false): array
    {
        $cacheKey = $isCluster ? 'clinical_conditions_sorted_evaluable_cluster' : 'clinical_conditions_sorted_evaluable';

        return Cache::remember($cacheKey, 3600, function () use ($isCluster) {
            return self::active()
                ->forFlow($isCluster)
                ->where('criteria_count', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('active_from')
                        ->orWhere('active_from', '<=', now()->toDateString());
                })
                ->orderByDesc('risk_tier')
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
        Cache::forget('clinical_conditions_sorted_evaluable');
        Cache::forget('clinical_conditions_sorted_evaluable_cluster');
    }
}

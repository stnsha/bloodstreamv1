<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResultLibrary extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'value',
        'code',
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'code' => null,
        'description' => null,
    ];

    /**
     * Static cache for ResultLibrary lookups (PERFORMANCE OPTIMIZATION)
     * Prevents repeated database queries within same request
     */
    protected static $cache = [];

    /**
     * Get ResultLibrary with static caching to avoid repeated queries
     * PERFORMANCE OPTIMIZATION
     *
     * @param string $code
     * @param string $value
     * @return ResultLibrary|null
     */
    public static function getCached($code, $value)
    {
        $cacheKey = $code . '|' . $value;

        if (!isset(static::$cache[$cacheKey])) {
            static::$cache[$cacheKey] = static::where('code', $code)
                ->where('value', $value)
                ->first();
        }

        return static::$cache[$cacheKey];
    }

    /**
     * Batch load multiple ResultLibrary records with caching
     * PERFORMANCE OPTIMIZATION
     *
     * @param string $code
     * @param array $values
     * @return \Illuminate\Support\Collection
     */
    public static function getBatchCached($code, array $values)
    {
        $uncachedValues = [];
        $result = collect([]);

        // Check cache first
        foreach ($values as $value) {
            $cacheKey = $code . '|' . $value;
            if (isset(static::$cache[$cacheKey])) {
                $result->put($value, static::$cache[$cacheKey]);
            } else {
                $uncachedValues[] = $value;
            }
        }

        // Load uncached values from database
        if (!empty($uncachedValues)) {
            $records = static::where('code', $code)
                ->whereIn('value', $uncachedValues)
                ->get()
                ->keyBy('value');

            foreach ($records as $value => $record) {
                $cacheKey = $code . '|' . $value;
                static::$cache[$cacheKey] = $record;
                $result->put($value, $record);
            }

            // Cache null for non-existent values
            foreach ($uncachedValues as $value) {
                if (!$result->has($value)) {
                    $cacheKey = $code . '|' . $value;
                    static::$cache[$cacheKey] = null;
                }
            }
        }

        return $result;
    }

    /**
     * Clear the static cache (useful for testing or long-running processes)
     */
    public static function clearCache()
    {
        static::$cache = [];
    }
}

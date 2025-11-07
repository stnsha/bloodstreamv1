<?php

namespace App\Models\Eurofins;

use App\Models\TestResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'test_result_id',
        'test_panel',
        'registered_by',
        'sampling_exam',
        'exam_date',
        'received_date',
        'overall_notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'registered_by'  => null,
        'overall_notes'  => null,
        'sampling_exam'  => null,
        'exam_date'  => null,
        'received_date'  => null
    ];

    /**
     * Get the user that owns the ReportRecord
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function test_result(): BelongsTo
    {
        return $this->belongsTo(TestResult::class, 'test_result_id', 'id');
    }
}
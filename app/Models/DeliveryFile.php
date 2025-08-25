<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryFile extends Model
{
    use HasFactory, SoftDeletes;

    const prcs = 'Processing';
    const compl = 'Completed';
    const fld = 'Failed';
    const ndt = 'No data';

    protected $fillable = [
        'lab_id',
        'sending_facility',
        'batch_id', //MessageControlId
        'json_content',
        'status',
    ];

    protected $casts = [
        'lab_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'lab_id' => null,
    ];

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function deliveryFileHistories(): HasMany
    {
        return $this->hasMany(DeliveryFileHistory::class);
    }
}
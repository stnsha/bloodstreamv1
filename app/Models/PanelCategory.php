<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PanelCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lab_id',
        'name'
    ];

    protected $casts = [
        'lab_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }
}
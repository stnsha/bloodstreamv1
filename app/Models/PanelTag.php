<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PanelTag extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lab_id',
        'panel_id',
        'name',
        'code',
    ];

    protected $casts = [
        'lab_id' => 'integer',
        'panel_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function panel(): BelongsTo
    {
        return $this->belongsTo(Panel::class, 'panel_id', 'id');
    }
}
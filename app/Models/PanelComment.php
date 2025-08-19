<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PanelComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['panel_id', 'identifier', 'comment', 'sequence'];

    protected $attributes = [
        'identifier' => null,
        'sequence' => null,
    ];

    public function panel(): BelongsTo
    {
        return $this->belongsTo(Panel::class, 'panel_id', 'id');
    }
}
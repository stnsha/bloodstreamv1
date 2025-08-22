<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Panel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lab_id',
        'master_panel_id',
        'code',
        'int_code',
        'sequence',
    ];

    protected $casts = [
        'lab_id' => 'integer',
        'master_panel_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function masterPanel(): BelongsTo
    {
        return $this->belongsTo(MasterPanel::class);
    }

    public function panelItems(): BelongsToMany
    {
        return $this->belongsToMany(PanelItem::class, 'panel_panel_items')
            ->withTimestamps();
    }

    public function panelComments(): HasMany
    {
        return $this->hasMany(PanelComment::class);
    }
}
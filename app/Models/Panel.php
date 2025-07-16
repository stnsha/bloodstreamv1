<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Panel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['lab_id', 'name', 'code', 'sequence', 'overall_notes'];

    public function panelItems(): HasMany
    {
        return $this->hasMany(PanelItem::class, 'panel_id', 'id');
    }
}

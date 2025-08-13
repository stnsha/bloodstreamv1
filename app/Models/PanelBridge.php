<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PanelBridge extends Model
{
    use HasFactory;

    protected $fillable = [
        'panel_panel_item_id',
        'old_panel_id',
        'old_panel_item_id',
    ];

    public function panel()
    {
        return $this->belongsTo(Panel::class);
    }
}
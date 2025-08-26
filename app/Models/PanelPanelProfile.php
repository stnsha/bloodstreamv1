<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PanelPanelProfile extends Model
{
    use HasFactory;

    protected $table = 'panel_panel_profiles';

    protected $fillable = [
        'panel_id',
        'panel_profile_id',
        'sequence',
    ];

    protected $casts = [
        'panel_id' => 'integer',
        'panel_profile_id' => 'integer',
        'sequence' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function panel(): BelongsTo
    {
        return $this->belongsTo(Panel::class);
    }

    public function panelProfile(): BelongsTo
    {
        return $this->belongsTo(PanelProfile::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PanelProfilesCount extends Model
{
    use HasFactory;

    protected $table = 'panel_profiles_count';

    protected $fillable = [
        'panel_profile_id',
        'count',
    ];

    protected $casts = [
        'panel_profile_id' => 'integer',
        'count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function panelProfile(): BelongsTo
    {
        return $this->belongsTo(PanelProfile::class);
    }
}

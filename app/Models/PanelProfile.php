<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PanelProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lab_id',
        'name',
        'code',
    ];

    protected $casts = [
        'lab_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'code' => null,
    ];

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function panels(): BelongsToMany
    {
        return $this->belongsToMany(Panel::class, 'panel_panel_profiles')
            ->withTimestamps();
    }

    public function testResultProfiles(): HasMany
    {
        return $this->hasMany(TestResultProfile::class, 'panel_profile_id', 'id');
    }
}
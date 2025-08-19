<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    protected $attributes = [
        'code' => null,
    ];

    public function testResultProfiles(): HasMany
    {
        return $this->hasMany(TestResultProfile::class, 'panel_profile_id', 'id');
    }
}
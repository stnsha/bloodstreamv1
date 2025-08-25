<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lab extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'path',
        'code',
        'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'path' => null,
    ];

    public function labCredentials(): HasMany
    {
        return $this->hasMany(LabCredential::class);
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    public function panelProfiles(): HasMany
    {
        return $this->hasMany(PanelProfile::class);
    }

    public function panels(): HasMany
    {
        return $this->hasMany(Panel::class);
    }

    public function panelItems(): HasMany
    {
        return $this->hasMany(PanelItem::class);
    }

    public function panelCategories(): HasMany
    {
        return $this->hasMany(PanelCategory::class);
    }

    public function panelTags(): HasMany
    {
        return $this->hasMany(PanelTag::class);
    }

    public function deliveryFiles(): HasMany
    {
        return $this->hasMany(DeliveryFile::class);
    }
}

<?php

namespace App\Models\Innoquest;

use App\Models\Lab;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TempIntCode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'panel_id',
        'int_code',
    ];

    protected $casts = [
        'panel_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function lab()
    {
        return $this->belongsTo(Lab::class);
    }
}

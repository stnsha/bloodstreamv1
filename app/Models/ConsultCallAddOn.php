<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultCallAddOn extends Model
{
    protected $table = 'consult_call_add_ons';

    protected $fillable = [
        'consult_call_id',
        'add_on_id',
    ];

    protected $casts = [
        'consult_call_id' => 'integer',
        'add_on_id' => 'integer',
    ];

    public function consultCall(): BelongsTo
    {
        return $this->belongsTo(ConsultCall::class, 'consult_call_id', 'id');
    }

    public function addOn(): BelongsTo
    {
        return $this->belongsTo(AddOn::class, 'add_on_id', 'id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryFileHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['delivery_file_id', 'message', 'err_code'];

    protected $casts = [
        'delivery_file_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function deliveryFile(): BelongsTo
    {
        return $this->belongsTo(DeliveryFile::class);
    }
}

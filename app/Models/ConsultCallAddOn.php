<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsultCallAddOn extends Model
{
    // Invoice Status (Add-On invoice)
    const INVOICE_STATUS_CONFIRMED = 1;

    const INVOICE_STATUS_COMPLETED = 2;

    protected $table = 'consult_call_add_ons';

    protected $fillable = [
        'consult_call_id',
        'add_on_id',
        'consult_call_detail_id',
        'invoice_id',
        'blood_test_sales_id',
        'invoice_status',
    ];

    protected $casts = [
        'consult_call_id' => 'integer',
        'add_on_id' => 'integer',
        'consult_call_detail_id' => 'integer',
        'blood_test_sales_id' => 'integer',
        'invoice_status' => 'integer',
    ];

    public function consultCall(): BelongsTo
    {
        return $this->belongsTo(ConsultCall::class, 'consult_call_id', 'id');
    }

    public function addOn(): BelongsTo
    {
        return $this->belongsTo(AddOn::class, 'add_on_id', 'id');
    }

    /**
     * Lab report(s) of the sale that bought this add-on (shared by every add-on on that invoice).
     */
    public function results(): HasMany
    {
        return $this->hasMany(ConsultCallAddOnResult::class, 'blood_test_sales_id', 'blood_test_sales_id');
    }
}

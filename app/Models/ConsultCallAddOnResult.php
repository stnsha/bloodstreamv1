<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultCallAddOnResult extends Model
{
    protected $table = 'consult_call_add_on_results';

    protected $fillable = [
        'consult_call_id',
        'consult_call_detail_id',
        'blood_test_sales_id',
        'test_result_id',
    ];

    protected $casts = [
        'consult_call_id' => 'integer',
        'consult_call_detail_id' => 'integer',
        'blood_test_sales_id' => 'integer',
        'test_result_id' => 'integer',
    ];

    public function consultCall(): BelongsTo
    {
        return $this->belongsTo(ConsultCall::class, 'consult_call_id', 'id');
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(ConsultCallDetails::class, 'consult_call_detail_id', 'id');
    }

    public function testResult(): BelongsTo
    {
        return $this->belongsTo(TestResult::class, 'test_result_id', 'id');
    }
}

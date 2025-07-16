<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryFile extends Model
{
    use HasFactory, SoftDeletes;

    const prcs = 'Processing';
    const compl = 'Completed';
    const fld = 'Failed';
    const ndt = 'No data';

    protected $fillable = [
        'lab_id',
        'sending_facility',
        'batch_id', //MessageControlId
        'test_result_id',
        'json_content',
        'status',
    ];
}

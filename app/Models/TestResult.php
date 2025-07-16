<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Info(
 *     version="1.0",
 *     title="Blood Stream v1 API",
 *     description="This API facilitates the secure and structured integration of blood test result data between external laboratory systems and Alpro’s internal infrastructure. It enables seamless transmission, standardization, and accessibility of diagnostic information for clinical interpretation by authorized healthcare professionals.",
 *     @OA\Contact(name="Digital Innovation")
 * )
 *  * @OA\Server(
 *      url="http://127.0.0.1:8000",
 *      description="Local Server"
 * )
 * 
 * @OA\Server(
 *      url="http://172.18.28.51:8002",
 *      description="MyHealth Server"
 * )
 *
 */

class TestResult extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'doctor_code_id',
        'patient_id',
        'ref_id',
        'bill_code',
        'lab_no',
        'collected_date',
        'received_date',
        'reported_date',
        'is_completed'
    ];
}

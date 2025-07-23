<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryFileHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['delivery_file_id', 'message', 'err_code'];
}

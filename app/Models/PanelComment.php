<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PanelComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['panel_item_id', 'identifier', 'comment', 'sequence'];
}

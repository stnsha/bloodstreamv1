<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PanelComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'panel_id',
        'master_panel_comment_id',
    ];

    protected $casts = [
        'panel_id' => 'integer',
        'master_panel_comment_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function panel(): BelongsTo
    {
        return $this->belongsTo(Panel::class);
    }

    public function masterPanelComment(): BelongsTo
    {
        return $this->belongsTo(MasterPanelComment::class, 'master_panel_comment_id');
    }

    public function testResultItems(): BelongsToMany
    {
        return $this->belongsToMany(TestResultItem::class, 'test_result_item_panel_comments')
                    ->withTimestamps();
    }
}
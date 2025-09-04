<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestResultComment extends Model
{
    use HasFactory;

    protected $table = 'test_result_item_panel_comments';

    protected $fillable = [
        'test_result_item_id',
        'panel_comment_id',
    ];

    protected $casts = [
        'test_result_item_id' => 'integer',
        'panel_comment_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'test_result_item_id' => null,
        'panel_comment_id' => null,
    ];

    public function testResultItem(): BelongsTo
    {
        return $this->belongsTo(TestResultItem::class, 'test_result_item_id', 'id');
    }

    public function panelComment(): BelongsTo
    {
        return $this->belongsTo(PanelComment::class, 'panel_comment_id', 'id');
    }
}
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('test_result_items', function (Blueprint $table) {
            // Composite index for updateOrCreate operations
            // Optimizes: WHERE test_result_id = ? AND panel_panel_item_id = ? AND deleted_at IS NULL
            // Also optimizes: WHERE test_result_id = ? (leftmost prefix for pre-fetch)
            $table->index(
                ['test_result_id', 'panel_panel_item_id', 'deleted_at'],
                'test_result_items_updateorcreate_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('test_result_items', function (Blueprint $table) {
            $table->dropIndex('test_result_items_updateorcreate_idx');
        });
        Schema::enableForeignKeyConstraints();
    }
};

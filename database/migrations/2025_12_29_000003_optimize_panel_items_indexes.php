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
        Schema::table('panel_items', function (Blueprint $table) {
            // Composite index for firstOrCreate operations
            // Optimizes: WHERE lab_id = ? AND master_panel_item_id = ? AND identifier = ? AND deleted_at IS NULL
            // Also optimizes: WHERE lab_id = ? (leftmost prefix)
            $table->index(
                ['lab_id', 'master_panel_item_id', 'identifier', 'deleted_at'],
                'panel_items_firstorcreate_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('panel_items', function (Blueprint $table) {
            $table->dropIndex('panel_items_firstorcreate_idx');
        });
    }
};

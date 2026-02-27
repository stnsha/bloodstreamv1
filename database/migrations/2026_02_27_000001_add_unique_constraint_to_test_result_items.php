<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove duplicate active rows, keeping the one with the highest id.
        // Duplicates arise because upsert() requires a database-level UNIQUE constraint
        // to detect conflicts; without it, MySQL performs plain INSERTs every time.
        DB::statement('
            DELETE t1
            FROM test_result_items t1
            INNER JOIN test_result_items t2
                ON  t1.test_result_id      = t2.test_result_id
                AND t1.panel_panel_item_id = t2.panel_panel_item_id
                AND t1.value = t2.value
                AND t1.sequence = t2.sequence
                AND t1.deleted_at          IS NULL
                AND t2.deleted_at          IS NULL
                AND t1.id < t2.id
        ');

        Schema::table('test_result_items', function (Blueprint $table) {
            $table->unique(
                ['test_result_id', 'panel_panel_item_id'],
                'test_result_items_unique_pair'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_result_items', function (Blueprint $table) {
            $table->dropUnique('test_result_items_unique_pair');
        });
    }
};

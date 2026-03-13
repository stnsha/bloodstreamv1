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
        // Remove duplicate active rows in batches to avoid a single long-running
        // transaction that would lock the table and risk PHP/MySQL timeout.
        // Each batch deletes at most 500 rows, keeping the highest-id copy.
        // The loop continues until no more duplicates remain.
        $batchSize = 500;

        // Pass 1: Remove exact duplicates (same value and sequence).
        // These are plain re-deliveries of the same payload.
        // MariaDB does not support LIMIT on multi-table DELETE, so we select
        // the IDs to remove first and delete by ID in batches.
        do {
            $affected = DB::delete('
                DELETE FROM test_result_items
                WHERE id IN (
                    SELECT id FROM (
                        SELECT t1.id
                        FROM test_result_items t1
                        INNER JOIN test_result_items t2
                            ON  t1.test_result_id      = t2.test_result_id
                            AND t1.panel_panel_item_id = t2.panel_panel_item_id
                            AND t1.value               = t2.value
                            AND t1.sequence            = t2.sequence
                            AND t1.deleted_at          IS NULL
                            AND t2.deleted_at          IS NULL
                            AND t1.id < t2.id
                        LIMIT ' . $batchSize . '
                    ) AS batch
                )
            ');
        } while ($affected > 0);

        // Pass 2: Remove any remaining duplicates on (test_result_id, panel_panel_item_id)
        // where value or sequence differs (e.g. amended results delivered twice).
        // Keep the highest id — the most recently inserted row — as the authoritative value.
        do {
            $affected = DB::delete('
                DELETE FROM test_result_items
                WHERE id IN (
                    SELECT id FROM (
                        SELECT t1.id
                        FROM test_result_items t1
                        INNER JOIN test_result_items t2
                            ON  t1.test_result_id      = t2.test_result_id
                            AND t1.panel_panel_item_id = t2.panel_panel_item_id
                            AND t1.deleted_at          IS NULL
                            AND t2.deleted_at          IS NULL
                            AND t1.id < t2.id
                        LIMIT ' . $batchSize . '
                    ) AS batch
                )
            ');
        } while ($affected > 0);

        $indexExists = collect(DB::select("SHOW INDEX FROM test_result_items WHERE Key_name = 'test_result_items_unique_pair'"))->isNotEmpty();

        if (! $indexExists) {
            Schema::table('test_result_items', function (Blueprint $table) {
                $table->unique(
                    ['test_result_id', 'panel_panel_item_id'],
                    'test_result_items_unique_pair'
                );
            });
        }
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

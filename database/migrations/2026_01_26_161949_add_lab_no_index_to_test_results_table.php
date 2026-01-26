<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds composite index on lab_no and deleted_at for optimizing
     * TestResult::where('lab_no', $lab_no)->first() queries in ProcessPanelResults job.
     *
     * Impact: Reduces O(n) table scan to O(log n) index lookup.
     * Critical for high-volume panel results processing (500+ requests/day).
     */
    public function up(): void
    {
        $indexExists = DB::select("
            SELECT COUNT(*) as count
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'test_results'
            AND INDEX_NAME = 'test_results_lab_no_deleted_at_index'
        ");

        if ($indexExists[0]->count === 0) {
            Schema::table('test_results', function (Blueprint $table) {
                $table->index(['lab_no', 'deleted_at'], 'test_results_lab_no_deleted_at_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexExists = DB::select("
            SELECT COUNT(*) as count
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'test_results'
            AND INDEX_NAME = 'test_results_lab_no_deleted_at_index'
        ");

        if ($indexExists[0]->count > 0) {
            Schema::table('test_results', function (Blueprint $table) {
                $table->dropIndex('test_results_lab_no_deleted_at_index');
            });
        }
    }
};

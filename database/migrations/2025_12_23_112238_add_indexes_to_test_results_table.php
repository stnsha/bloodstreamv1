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
        Schema::table('test_results', function (Blueprint $table) {
            // Index for ref_id lookups (primary slow query optimization)
            // Optimizes: WHERE ref_id = ? AND is_completed = ? AND collected_date BETWEEN ? ORDER BY created_at
            $table->index(
                ['ref_id', 'is_completed', 'collected_date', 'created_at', 'deleted_at'],
                'test_results_ref_lookup_idx'
            );

            // Index for unreviewed results batch processing
            // Optimizes: WHERE is_reviewed = ? AND is_completed = ? AND collected_date BETWEEN ? ORDER BY id
            $table->index(
                ['is_reviewed', 'is_completed', 'collected_date', 'id', 'deleted_at'],
                'test_results_unreviewed_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_results', function (Blueprint $table) {
            // Drop the indexes
            $table->dropIndex('test_results_ref_lookup_idx');
            $table->dropIndex('test_results_unreviewed_idx');
        });
    }
};

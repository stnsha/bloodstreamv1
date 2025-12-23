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
        Schema::table('jobs', function (Blueprint $table) {
            // Add partition column (0-9) based on id % 10
            // This spreads jobs across 10 "buckets" to reduce lock contention
            DB::statement('ALTER TABLE jobs ADD COLUMN `partition` TINYINT UNSIGNED GENERATED ALWAYS AS (MOD(id, 10)) STORED AFTER `queue`');

            // Add index on partition to segment worker queries
            // This index helps distribute workers across different partitions
            $table->index(['queue', 'partition', 'available_at', 'id'], 'jobs_queue_partition_available_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            // Drop the partition index first
            $table->dropIndex('jobs_queue_partition_available_id_idx');

            // Drop the partition column
            $table->dropColumn('partition');
        });
    }
};

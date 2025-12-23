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
            // Add partition column (0-9) as a regular column
            // Use random distribution since we can't use AUTO_INCREMENT in triggers
            $table->unsignedTinyInteger('partition')->default(0)->after('queue');

            // Add index on partition to segment worker queries
            // This index helps distribute workers across different partitions
            $table->index(['queue', 'partition', 'available_at', 'id'], 'jobs_queue_partition_available_id_idx');
        });

        // Create trigger to randomly assign partition (0-9) on INSERT
        // This distributes jobs across 10 buckets to reduce lock contention
        DB::unprepared('
            CREATE TRIGGER jobs_set_partition_before_insert
            BEFORE INSERT ON jobs
            FOR EACH ROW
            BEGIN
                SET NEW.partition = FLOOR(RAND() * 10);
            END
        ');

        // Update existing rows with random partition values
        DB::statement('UPDATE jobs SET partition = FLOOR(RAND() * 10)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the trigger first
        DB::unprepared('DROP TRIGGER IF EXISTS jobs_set_partition_before_insert');

        Schema::table('jobs', function (Blueprint $table) {
            // Drop the partition index
            $table->dropIndex('jobs_queue_partition_available_id_idx');

            // Drop the partition column
            $table->dropColumn('partition');
        });
    }
};

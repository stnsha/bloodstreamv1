<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Exception;

class AddMigrationPerformanceIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Wrap all index creation in try-catch to handle existing indexes
        try {
            Schema::table('migration_batches', function (Blueprint $table) {
                // Index for status queries
                $table->index('status', 'migration_batches_status_idx');
            });
        } catch (Exception $e) {
            // Index may already exist, skip
        }

        try {
            Schema::table('migration_batches', function (Blueprint $table) {
                // Index for cleanup queries
                $table->index('created_at', 'migration_batches_created_at_idx');
            });
        } catch (Exception $e) {
            // Index may already exist, skip
        }

        try {
            Schema::table('migration_batches', function (Blueprint $table) {
                // Composite for status + created_at
                $table->index(['status', 'created_at'], 'migration_batches_status_created_idx');
            });
        } catch (Exception $e) {
            // Index may already exist, skip
        }

        try {
            Schema::table('migration_batch_items', function (Blueprint $table) {
                // Composite index for batch + status queries
                $table->index(['batch_id', 'status'], 'migration_batch_items_batch_status_idx');
            });
        } catch (Exception $e) {
            // Index may already exist, skip
        }

        try {
            Schema::table('migration_batch_items', function (Blueprint $table) {
                // Index for ref_id lookups
                $table->index('ref_id', 'migration_batch_items_ref_id_idx');
            });
        } catch (Exception $e) {
            // Index may already exist, skip
        }

        try {
            Schema::table('migration_batch_items', function (Blueprint $table) {
                // Index for attempt count (retry queries)
                $table->index('attempt_count', 'migration_batch_items_attempt_idx');
            });
        } catch (Exception $e) {
            // Index may already exist, skip
        }

        // Optimize test_result_items table (heavy write load)
        // Using try-catch to handle if index already exists
        try {
            Schema::table('test_result_items', function (Blueprint $table) {
                // Composite index for upsert operations
                $table->index(
                    ['test_result_id', 'panel_id', 'panel_item_id'],
                    'test_result_items_upsert_idx'
                );
            });
        } catch (Exception $e) {
            // Index may already exist, skip
        }

        // Optimize patients table (frequent firstOrCreate)
        // Using try-catch to handle if index already exists
        try {
            Schema::table('patients', function (Blueprint $table) {
                $table->index('icno', 'patients_icno_idx');
            });
        } catch (Exception $e) {
            // Index may already exist or icno is unique key, skip
        }

        // Optimize doctors table (frequent firstOrCreate)
        try {
            Schema::table('doctors', function (Blueprint $table) {
                // Composite index for lab_id + name
                $table->index(['lab_id', 'name'], 'doctors_lab_name_idx');
            });
        } catch (Exception $e) {
            // Index may already exist, skip
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('migration_batches', function (Blueprint $table) {
            $table->dropIndex('migration_batches_status_idx');
            $table->dropIndex('migration_batches_created_at_idx');
            $table->dropIndex('migration_batches_status_created_idx');
        });

        Schema::table('migration_batch_items', function (Blueprint $table) {
            $table->dropIndex('migration_batch_items_batch_status_idx');
            $table->dropIndex('migration_batch_items_ref_id_idx');
            $table->dropIndex('migration_batch_items_attempt_idx');
        });

        try {
            Schema::table('test_result_items', function (Blueprint $table) {
                $table->dropIndex('test_result_items_upsert_idx');
            });
        } catch (Exception $e) {
            // Index may not exist, skip
        }

        try {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropIndex('patients_icno_idx');
            });
        } catch (Exception $e) {
            // Index may not exist, skip
        }

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropIndex('doctors_lab_name_idx');
        });
    }
}

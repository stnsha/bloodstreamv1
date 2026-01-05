<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMigrationPerformanceIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('migration_batches', function (Blueprint $table) {
            $table->index('created_at', 'migration_batches_created_at_idx');
            $table->index(['status', 'created_at'], 'migration_batches_status_created_idx');
        });

        Schema::table('migration_batch_items', function (Blueprint $table) {
            $table->index(['batch_id', 'status'], 'migration_batch_items_batch_status_idx');
            $table->index('ref_id', 'migration_batch_items_ref_id_idx');
            $table->index('attempt_count', 'migration_batch_items_attempt_idx');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->index('icno', 'patients_icno_idx');
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->index(['lab_id', 'name'], 'doctors_lab_name_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('migration_batches', function (Blueprint $table) {
            $table->dropIndex('migration_batches_created_at_idx');
            $table->dropIndex('migration_batches_status_created_idx');
        });

        Schema::table('migration_batch_items', function (Blueprint $table) {
            $table->dropIndex('migration_batch_items_batch_status_idx');
            $table->dropIndex('migration_batch_items_ref_id_idx');
            $table->dropIndex('migration_batch_items_attempt_idx');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('patients_icno_idx');
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropIndex('doctors_lab_name_idx');
        });

        Schema::enableForeignKeyConstraints();
    }
}

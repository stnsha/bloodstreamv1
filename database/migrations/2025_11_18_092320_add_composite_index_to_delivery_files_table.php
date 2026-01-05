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
        // Add composite index for delivery_files lookup performance
        // This fixes slow query (5.8s → <10ms):
        // select * from `delivery_files`
        // where (`lab_id` = ? and `sending_facility` = ? and `batch_id` = ?)
        // and `delivery_files`.`deleted_at` is null limit 1
        Schema::table('delivery_files', function (Blueprint $table) {
            $table->index(
                ['lab_id', 'sending_facility', 'batch_id', 'deleted_at'],
                'idx_delivery_files_lookup'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('delivery_files', function (Blueprint $table) {
            $table->dropIndex('idx_delivery_files_lookup');
        });
        Schema::enableForeignKeyConstraints();
    }
};

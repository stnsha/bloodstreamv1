<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Exception;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Schema::table('test_result_items', function (Blueprint $table) {
                $table->dropIndex('test_result_items_upsert_idx');
            });
        } catch (Exception $e) {
            // Index may not exist if the previous migration failed, continue
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed - the index was invalid anyway
    }
};

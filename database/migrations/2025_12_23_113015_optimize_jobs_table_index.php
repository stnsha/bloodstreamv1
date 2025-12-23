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
        Schema::table('jobs', function (Blueprint $table) {
            // Add optimized index for queue worker query
            // Optimizes: WHERE queue = ? AND available_at <= ? ORDER BY id
            // This eliminates filesort by including id in the index
            $table->index(['queue', 'available_at', 'id'], 'jobs_queue_available_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            // Drop the optimized index
            $table->dropIndex('jobs_queue_available_id_index');
        });
    }
};

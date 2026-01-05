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
            // Add composite index to optimize queue:work query
            // This index helps with: WHERE queue = ? AND reserved_at IS NULL/<=? AND available_at <= ?
            $table->index(['queue', 'reserved_at', 'available_at'], 'jobs_queue_reserved_available_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('jobs', function (Blueprint $table) {
            // Drop the composite index
            $table->dropIndex('jobs_queue_reserved_available_index');
        });
        Schema::enableForeignKeyConstraints();
    }
};

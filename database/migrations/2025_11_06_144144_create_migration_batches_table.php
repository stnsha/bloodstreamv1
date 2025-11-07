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
        Schema::create('migration_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_uuid')->unique();
            $table->integer('total_reports')->default(0);
            $table->integer('processed')->default(0);
            $table->integer('success')->default(0);
            $table->integer('failed')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'partial_failure'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('batch_uuid');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('migration_batches');
    }
};

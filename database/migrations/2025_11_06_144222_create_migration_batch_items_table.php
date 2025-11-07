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
        Schema::create('migration_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('migration_batches')->onDelete('cascade');
            $table->string('ref_id')->nullable();
            $table->unsignedBigInteger('test_result_id')->nullable();
            $table->longText('report_data')->nullable();
            $table->enum('status', ['pending', 'processing', 'success', 'failed'])->default('pending');
            $table->integer('attempt_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('test_result_id')->references('id')->on('test_results')->onDelete('set null');
            $table->index('batch_id');
            $table->index('status');
            $table->index('ref_id');
            $table->index('test_result_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('migration_batch_items');
    }
};

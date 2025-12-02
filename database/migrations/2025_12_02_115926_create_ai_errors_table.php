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
        Schema::create('ai_errors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('test_result_id')->unique();
            $table->integer('http_status')->nullable();
            $table->text('error_message');
            $table->json('compiled_data')->nullable();
            $table->integer('attempt_count')->default(1);
            $table->timestamps();

            $table->foreign('test_result_id')->references('id')->on('test_results')->onDelete('cascade');

            $table->index('http_status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_errors');
    }
};

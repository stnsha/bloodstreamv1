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
        Schema::create('doctor_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('test_result_id');
            $table->json('compiled_results');
            $table->longText('review');
            $table->boolean('is_sync')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('test_result_id')->references('id')->on('test_results')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_reviews');
    }
};

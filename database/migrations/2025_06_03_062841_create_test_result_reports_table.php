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
        Schema::create('test_result_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('test_result_id');
            $table->unsignedBigInteger('panel_id');
            $table->longText('text');
            $table->boolean('is_completed')->default(false);
            $table->timestamps();

            $table->foreign('test_result_id')->references('id')->on('test_results')->onDelete('cascade');
            $table->foreign('panel_id')->references('id')->on('panels')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_result_reports');
    }
};

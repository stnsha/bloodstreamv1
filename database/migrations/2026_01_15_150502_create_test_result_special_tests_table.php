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
        Schema::create('test_result_special_tests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('test_result_id')->nullable();
            $table->unsignedBigInteger('panel_panel_item_id')->nullable();
            $table->string('value')->nullable();
            $table->unsignedBigInteger('panel_interpretation_id')->nullable();
            $table->timestamps();

            $table->foreign('test_result_id')->references('id')->on('test_results')->onDelete('cascade');
            $table->foreign('panel_panel_item_id')->references('id')->on('panel_panel_items')->onDelete('cascade');
            $table->foreign('panel_interpretation_id')->references('id')->on('panel_interpretations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_result_special_tests');
    }
};

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
        Schema::create('clinical_conditions', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('description');
            $table->integer('risk_tier')->default(1); //1 - low, 2 - medium, 3 - high, 0 - no risk
            $table->string('evaluator');
            $table->unsignedTinyInteger('criteria_count');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinical_conditions');
    }
};

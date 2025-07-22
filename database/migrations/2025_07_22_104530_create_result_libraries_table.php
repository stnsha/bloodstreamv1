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
        Schema::create('result_libraries', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('value');
            $table->string('code')->nullable();
            $table->string('description')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Composite unique constraint on value and code combination
            $table->unique(['value', 'code'], 'hl7_value_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('result_libraries');
    }
};
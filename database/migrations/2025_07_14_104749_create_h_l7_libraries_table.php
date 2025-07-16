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
        Schema::create('hl7_libraries', function (Blueprint $table) {
            $table->id();
            $table->string('value');
            $table->string('description');
            $table->string('code');
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
        Schema::dropIfExists('hl7_libraries');
    }
};

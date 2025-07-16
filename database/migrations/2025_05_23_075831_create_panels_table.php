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
        Schema::create('panels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_id');
            $table->string('name');
            $table->string('code');
            $table->string('sequence')->nullable();
            $table->string('overall_notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('lab_id')->references('id')->on('labs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('panels');
    }
};

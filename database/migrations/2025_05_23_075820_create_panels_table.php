<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
            $table->unsignedBigInteger('panel_category_id')->nullable();
            $table->string('name');
            $table->string('code');
            $table->string('sequence')->nullable();
            $table->string('overall_notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('lab_id')->references('id')->on('labs')->onDelete('cascade');
            $table->foreign('panel_category_id')->references('id')->on('panel_categories')->onDelete('cascade');

            // Add unique constraint to prevent duplicate panels for same lab
            $table->unique(['lab_id', 'code'], 'panels_lab_id_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('panels');
    }
};

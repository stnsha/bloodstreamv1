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
        Schema::create('panel_panel_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panel_profile_id');
            $table->unsignedBigInteger('panel_id');
            $table->unsignedBigInteger('panel_category_id')->nullable();
            $table->integer('sequence')->nullable();
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('panel_id')->references('id')->on('panels')->onDelete('cascade');
            $table->foreign('panel_profile_id')->references('id')->on('panel_profiles')->onDelete('cascade');
            $table->foreign('panel_category_id')->references('id')->on('panel_categories')->onDelete('set null');
            
            // Ensure unique combination
            $table->unique(['panel_id', 'panel_profile_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('panel_panel_profiles');
    }
};
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
        Schema::create('panel_panel_item', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panel_id');
            $table->unsignedBigInteger('panel_item_id');
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('panel_id')->references('id')->on('panels')->onDelete('cascade');
            $table->foreign('panel_item_id')->references('id')->on('panel_items')->onDelete('cascade');
            
            // Ensure unique combination
            $table->unique(['panel_id', 'panel_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('panel_panel_item');
    }
};

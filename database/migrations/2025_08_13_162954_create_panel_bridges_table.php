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
        Schema::create('panel_bridges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panel_panel_item_id');
            $table->unsignedBigInteger('old_panel_id');
            $table->unsignedBigInteger('old_panel_item_id');
            $table->timestamps();

            $table->foreign('panel_panel_item_id')->references('id')->on('panel_panel_items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('panel_bridges');
    }
};
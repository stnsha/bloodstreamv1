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
        Schema::create('panel_interpretations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panel_panel_item_id');
            $table->text('range')->nullable()->comment('Range of values for panel');
            $table->text('interpretation')->comment('Interpretation for the given range');
            $table->timestamps();

            $table->foreign('panel_panel_item_id')->references('id')->on('panel_panel_items')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('panel_interpretations');
    }
};

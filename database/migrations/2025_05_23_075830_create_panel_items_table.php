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
        Schema::create('panel_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_id');
            $table->unsignedBigInteger('master_panel_item_id')->nullable();
            $table->string('name')->nullable();
            $table->string('unit')->nullable();
            $table->string('identifier')->nullable(); //store temp panel item name if not exist in master, delete once updated
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('lab_id')->references('id')->on('labs')->onDelete('cascade');
            $table->foreign('master_panel_item_id')->references('id')->on('master_panel_items')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('panel_items');
    }
};
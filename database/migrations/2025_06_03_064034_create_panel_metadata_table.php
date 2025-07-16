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
        Schema::create('panel_metadata', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panel_item_id');
            $table->string('ordinal_id')->nullable();
            $table->string('type')->nullable();
            $table->string('identifier')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('panel_item_id')->references('id')->on('panel_items')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('panel_metadata');
    }
};

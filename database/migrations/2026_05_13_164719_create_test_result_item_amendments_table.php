<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_result_item_amendments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('test_result_item_id');
            $table->unsignedBigInteger('test_result_id');
            $table->unsignedBigInteger('panel_panel_item_id');
            $table->unsignedBigInteger('reference_range_id')->nullable();
            $table->string('value')->nullable();
            $table->string('flag')->nullable();
            $table->integer('sequence');
            $table->timestamps();

            $table->foreign('test_result_item_id')->references('id')->on('test_result_items')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_result_item_amendments');
    }
};

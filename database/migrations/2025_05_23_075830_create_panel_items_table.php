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
            $table->unsignedBigInteger('panel_id');
            $table->string('name');
            $table->string('decimal_point')->nullable();
            $table->string('unit')->nullable();
            $table->string('sequence')->nullable();
            $table->string('result_type')->nullable(); //get from json
            $table->string('identifier')->nullable();
            $table->string('code')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('panel_id')->references('id')->on('panels')->onDelete('cascade');
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

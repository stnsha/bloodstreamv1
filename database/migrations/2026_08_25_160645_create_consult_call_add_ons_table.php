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
        Schema::create('consult_call_add_ons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consult_call_id');
            $table->unsignedBigInteger('add_on_id');
            $table->timestamps();

            $table->foreign('consult_call_id')->references('id')->on('consult_calls')->onDelete('cascade');
            $table->foreign('add_on_id')->references('id')->on('add_ons')->onDelete('cascade');
            $table->unique(['consult_call_id', 'add_on_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consult_call_add_ons');
    }
};

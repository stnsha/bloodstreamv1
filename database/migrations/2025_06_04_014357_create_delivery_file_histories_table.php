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
        Schema::create('delivery_file_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_file_id');
            $table->longText('message');
            $table->string('err_code');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('delivery_file_id')->references('id')->on('delivery_files')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_file_histories');
    }
};

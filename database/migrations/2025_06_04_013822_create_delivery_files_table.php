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
        Schema::create('delivery_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_id');
            $table->string('sending_facility');
            $table->string('batch_id');
            $table->unsignedBigInteger('test_result_id')->nullable();
            $table->longText('json_content');
            $table->string('status');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('lab_id')->references('id')->on('labs')->onDelete('cascade');
            $table->foreign('test_result_id')->references('id')->on('test_results')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_files');
    }
};

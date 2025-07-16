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
        Schema::create('test_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_code_id');
            $table->unsignedBigInteger('patient_id');
            $table->string('ref_id')->nullable();
            $table->string('bill_code')->nullable();
            $table->string('lab_no');
            $table->dateTime('collected_date')->nullable();
            $table->dateTime('received_date')->nullable();
            $table->dateTime('reported_date')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('doctor_code_id')->references('id')->on('doctor_codes')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_results');
    }
};

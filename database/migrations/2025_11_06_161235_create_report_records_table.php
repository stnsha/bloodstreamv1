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
        Schema::create('report_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('test_result_id');
            $table->string('test_panel');
            $table->string('registered_by')->nullable();
            $table->date('sampling_exam')->nullable();
            $table->date('exam_date')->nullable();
            $table->date('received_date')->nullable();
            $table->longText('overall_notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('test_result_id')->references('id')->on('test_results')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_records');
    }
};
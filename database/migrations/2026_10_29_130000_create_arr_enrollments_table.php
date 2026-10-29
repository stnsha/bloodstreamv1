<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ARR enrollment: the cluster (ARR) counterpart of consult_calls. Only the
     * enrollment itself is stored here; documentation and follow-up are managed
     * outside this system.
     */
    public function up(): void
    {
        Schema::create('arr_enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('test_result_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('customer_id'); // from octopus
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->dateTime('enrollment_date');
            $table->unsignedBigInteger('handled_by')->nullable(); //staff_id
            $table->softDeletes();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('outlet_id');

            $table->foreign('test_result_id')->references('id')->on('test_results')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arr_enrollments');
    }
};

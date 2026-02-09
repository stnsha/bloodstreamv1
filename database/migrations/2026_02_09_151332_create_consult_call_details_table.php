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
        Schema::create('consult_call_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consult_call_id');
            $table->unsignedTinyInteger('clinical_condition_id');
            $table->unsignedBigInteger('test_result_id'); //for pdf
            $table->longText('diagnosis');
            $table->longText('treatment_plan');
            $table->boolean('rx_issued')->default(false);
            $table->integer('action'); //1 - refer outlet/internal clinic, 2 - refer external clinic/specialist, 3 - end process
            $table->integer('consult_status')->default(0); //0 - pending, 1 - completed, 2 - no show, 3 - cancelled
            $table->integer('process_status')->default(1); //1 - active, 2 - escalated, 3 - closed
            $table->unsignedBigInteger('consulted_by'); //staff_id
            $table->dateTime('consult_date')->nullable();
            $table->longText('remarks')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('consult_call_id')->references('id')->on('consult_calls')->onDelete('cascade');
            $table->foreign('clinical_condition_id')->references('id')->on('clinical_conditions')->onDelete('cascade');
            $table->foreign('test_result_id')->references('id')->on ('test_results')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consult_call_details');
    }
};

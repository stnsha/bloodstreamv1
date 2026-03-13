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
        Schema::create('consult_call_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consult_call_id');
            $table->integer('followup_type')->default(0); //0 - none, 1 - blood test + review, 2 - review only
            $table->integer('next_followup')->default(0); //0 - none, 1 - 1 month, 2 - 3 months, 3 - 6 months
            $table->dateTime('followup_date')->nullable();
            $table->boolean('is_blood_test_required')->default(false);
            $table->integer('mode_of_conversion'); //1 - outlet 
            $table->unsignedBigInteger('referral_to')->nullable(); //outlet_id
            $table->integer('my_referral_status')->default(0); // 0 - none, 1 - referred, 2 - acknowledged, 3 - completed
            $table->unsignedBigInteger('my_referral_id')->nullable();
            $table->integer('followup_reminder')->default(0); //0 - pending, 1 - completed, 2 - rescheduled, 3 - cancelled
            $table->dateTime('rescheduled_date')->nullable();
            $table->longText('remarks')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('consult_call_id')->references('id')->on('consult_calls')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consult_call_follow_ups');
    }
};

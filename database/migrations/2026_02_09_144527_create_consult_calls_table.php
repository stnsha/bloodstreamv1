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
        Schema::create('consult_calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('customer_id'); // from octopus
            $table->boolean('is_eligible')->default(false);
            $table->dateTime('enrollment_date');
            $table->integer('enrollment_type')->default(1); //1 - primary, 2 - follow-up
            $table->integer('consent_call_status')->default(0); //0 - pending, 1 - obtained, 2 - refused
            $table->dateTime('consent_call_date')->nullable();
            $table->integer('scheduled_status')->default(0); //0 - pending, 1 - confirmed, 2 - rescheduled, 3 - cancelled
            $table->dateTime('scheduled_call_date')->nullable();
            $table->unsignedBigInteger('handled_by'); //staff_id
            $table->integer('mode_of_consultation')->default(0); //0 - pending, 1 - phone, 2 - google meet, 3 - whatsapp
            $table->dateTime('closure_date')->nullable();
            $table->longText('final_remarks')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consult_calls');
    }
};

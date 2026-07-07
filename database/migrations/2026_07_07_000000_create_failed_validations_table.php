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
        Schema::create('failed_validations', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->index();
            $table->unsignedBigInteger('lab_id');
            $table->string('lab_no')->nullable()->index();
            $table->string('reason', 32)->default('validation_error');
            $table->text('missing_details')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_validations');
    }
};

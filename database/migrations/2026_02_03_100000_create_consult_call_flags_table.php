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
        Schema::create('consult_call_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_result_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('condition_id')->default(0);
            $table->string('condition_description')->nullable();
            $table->boolean('api_sent')->default(false);
            $table->timestamp('api_sent_at')->nullable();
            $table->text('api_response')->nullable();
            $table->timestamps();

            $table->unique('test_result_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consult_call_flags');
    }
};

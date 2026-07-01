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
        Schema::create('panel_profiles_count', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panel_profile_id');
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->foreign('panel_profile_id')->references('id')->on('panel_profiles')->onDelete('cascade');
            $table->unique('panel_profile_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('panel_profiles_count');
    }
};

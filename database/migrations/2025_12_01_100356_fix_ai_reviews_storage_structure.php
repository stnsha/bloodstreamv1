<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Change ai_response from JSON to longText to correctly store HTML string
     */
    public function up(): void
    {
        Schema::table('ai_reviews', function (Blueprint $table) {
            $table->longText('ai_response')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_reviews', function (Blueprint $table) {
            $table->json('ai_response')->nullable()->change();
        });
    }
};

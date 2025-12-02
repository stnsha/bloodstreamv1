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
        Schema::table('ai_reviews', function (Blueprint $table) {
            $table->dropColumn('is_successful');
            $table->dropColumn('error_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_reviews', function (Blueprint $table) {
            $table->boolean('is_successful')->default(false);
            $table->text('error_message')->nullable();
        });
    }
};

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
        Schema::table('ai_errors', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['test_result_id']);

            // Drop the unique constraint on test_result_id
            $table->dropUnique(['test_result_id']);

            // Re-add foreign key without unique constraint
            $table->foreign('test_result_id')->references('id')->on('test_results')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_errors', function (Blueprint $table) {
            // Drop foreign key
            $table->dropForeign(['test_result_id']);

            // Re-add the unique constraint if rolled back
            $table->unique('test_result_id');

            // Re-add foreign key
            $table->foreign('test_result_id')->references('id')->on('test_results')->onDelete('cascade');
        });
    }
};

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
        Schema::table('delivery_files', function (Blueprint $table) {
            // First drop the foreign key constraint if it exists
            $table->dropForeign(['test_result_id']);
            // Then drop the column
            $table->dropColumn('test_result_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_files', function (Blueprint $table) {
            // Add the column back
            $table->unsignedBigInteger('test_result_id')->nullable()->after('batch_id');
            // Add the foreign key constraint back
            $table->foreign('test_result_id')->references('id')->on('test_results')->onDelete('cascade');
        });
    }
};

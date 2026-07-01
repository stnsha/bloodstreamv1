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
        Schema::table('incomplete_test_results', function (Blueprint $table) {
            $table->boolean('was_reviewed')->default(false)->after('actual_panel_count');
            $table->unsignedBigInteger('ai_review_id')->nullable()->after('was_reviewed');

            $table->foreign('ai_review_id')->references('id')->on('ai_reviews')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incomplete_test_results', function (Blueprint $table) {
            $table->dropForeign(['ai_review_id']);
            $table->dropColumn(['was_reviewed', 'ai_review_id']);
        });
    }
};

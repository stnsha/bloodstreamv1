<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI review hold state for the add-on / consult-call release flow.
     *
     * When a completed test result matches a clinical condition whose type is
     * "AO" or "CC + AO", the AI review is not dispatched automatically. Instead
     * ai_review_held_at is stamped and the review waits until a doctor sets
     * "Release Doctor Review? = Yes" on the completed consultation, which stamps
     * ai_review_released_at and dispatches SendToAIServer.
     *
     * held : ai_review_held_at IS NOT NULL AND ai_review_released_at IS NULL
     * live : ai_review_released_at IS NOT NULL OR ai_review_held_at IS NULL
     */
    public function up(): void
    {
        Schema::table('test_results', function (Blueprint $table) {
            $table->timestamp('ai_review_held_at')->nullable()->after('is_reviewed');
            $table->timestamp('ai_review_released_at')->nullable()->after('ai_review_held_at');
            $table->index('ai_review_held_at');
        });
    }

    public function down(): void
    {
        Schema::table('test_results', function (Blueprint $table) {
            $table->dropIndex(['ai_review_held_at']);
            $table->dropColumn(['ai_review_held_at', 'ai_review_released_at']);
        });
    }
};

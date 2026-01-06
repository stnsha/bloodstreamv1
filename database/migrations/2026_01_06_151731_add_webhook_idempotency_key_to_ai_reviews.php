<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds webhook_idempotency_key column to ai_reviews table for idempotent webhook processing.
     * This prevents duplicate processing of the same webhook if the AI server retries delivery.
     */
    public function up(): void
    {
        Schema::table('ai_reviews', function (Blueprint $table) {
            // Add webhook idempotency key for preventing duplicate webhook processing
            $table->string('webhook_idempotency_key')->nullable()->unique()->after('processing_status');

            // Add index for faster lookups during webhook processing
            $table->index('webhook_idempotency_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_reviews', function (Blueprint $table) {
            $table->dropUnique(['webhook_idempotency_key']);
            $table->dropIndex(['webhook_idempotency_key']);
            $table->dropColumn('webhook_idempotency_key');
        });
    }
};

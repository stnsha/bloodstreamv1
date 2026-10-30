<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per test result sent to the MyHealth lab feed (Insyte), tracking
     * the delivery the way ai_reviews tracks the AI review. payload is the
     * 2-year patient profile last sent (same idea as ai_reviews.compiled_results);
     * it is cleared after 30 days by myhealth:purge-feed-payloads, the row is kept.
     */
    public function up(): void
    {
        Schema::create('myhealth_feed_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('test_result_id')->unique();
            $table->string('status', 16)->index(); // PENDING, SENT, FAILED, SKIPPED
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->json('payload')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);

            $table->foreign('test_result_id')->references('id')->on('test_results')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('myhealth_feed_deliveries');
    }
};

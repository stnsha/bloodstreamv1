<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lab report produced by an add-on sale, linked back to the consult call.
     *
     * Keyed by blood_test_sales_id (the number in test_results.ref_id). Rows are only
     * created once the existing completeness check has passed (from
     * TestResultCompletionDispatcher), so a row means every panel has arrived.
     * test_result_id is unique so re-deliveries never attach the same report twice.
     */
    public function up(): void
    {
        Schema::create('consult_call_add_on_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consult_call_id');
            $table->unsignedBigInteger('consult_call_detail_id')->nullable();
            $table->unsignedBigInteger('blood_test_sales_id');
            $table->unsignedBigInteger('test_result_id');
            $table->timestamps();

            $table->index('blood_test_sales_id');
            $table->unique('test_result_id');
            $table->foreign('consult_call_id')->references('id')->on('consult_calls')->onDelete('cascade');
            $table->foreign('consult_call_detail_id')->references('id')->on('consult_call_details')->nullOnDelete();
            $table->foreign('test_result_id')->references('id')->on('test_results')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consult_call_add_on_results');
    }
};

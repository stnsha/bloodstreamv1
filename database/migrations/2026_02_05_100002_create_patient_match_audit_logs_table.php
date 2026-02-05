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
        Schema::create('patient_match_audit_logs', function (Blueprint $table) {
            $table->id();

            // References
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedBigInteger('match_candidate_id')->nullable();
            $table->unsignedBigInteger('patient_customer_link_id')->nullable();

            // Action
            $table->enum('action', [
                'match_attempted',
                'candidates_found',
                'no_candidates_found',
                'candidate_approved',
                'candidate_rejected',
                'link_created',
                'link_removed'
            ]);

            // Data snapshot
            $table->json('input_data')->comment('Search parameters used');
            $table->json('output_data')->nullable()->comment('Results found');
            $table->json('score_breakdown')->nullable()->comment('Detailed scoring breakdown');

            // Context
            $table->enum('triggered_by', ['system', 'admin', 'job', 'api'])->default('system');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('job_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();

            // Timestamps
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index('patient_id');
            $table->index('customer_id');
            $table->index('action');
            $table->index('created_at');
            $table->index('triggered_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_match_audit_logs');
    }
};

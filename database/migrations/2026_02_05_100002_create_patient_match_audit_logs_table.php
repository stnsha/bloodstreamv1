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

            // Related entities (all nullable for flexibility)
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedBigInteger('match_candidate_id')->nullable();
            $table->unsignedBigInteger('patient_customer_link_id')->nullable();

            // Action tracking
            $table->enum('action', [
                'match_attempted',
                'candidates_found',
                'no_candidates_found',
                'candidate_approved',
                'candidate_rejected',
                'link_created',
                'link_removed'
            ]);

            // Data payloads
            $table->json('input_data');
            $table->json('output_data')->nullable();
            $table->json('score_breakdown')->nullable();

            // Trigger context
            $table->enum('triggered_by', ['system', 'admin', 'job', 'api'])->default('system');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('job_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();

            // Only created_at for audit logs (no updates)
            $table->timestamp('created_at')->useCurrent();

            // Indexes for efficient querying
            $table->index('patient_id', 'idx_pmal_patient_id');
            $table->index('customer_id', 'idx_pmal_customer_id');
            $table->index('match_candidate_id', 'idx_pmal_match_candidate_id');
            $table->index('patient_customer_link_id', 'idx_pmal_link_id');
            $table->index('action', 'idx_pmal_action');
            $table->index('triggered_by', 'idx_pmal_triggered_by');
            $table->index('user_id', 'idx_pmal_user_id');
            $table->index('created_at', 'idx_pmal_created_at');
            $table->index(['patient_id', 'action'], 'idx_pmal_patient_action');
            $table->index(['action', 'created_at'], 'idx_pmal_action_created');

            // Foreign keys (nullable references, no cascade delete to preserve audit history)
            $table->foreign('patient_id')
                ->references('id')
                ->on('patients')
                ->onDelete('set null');

            $table->foreign('match_candidate_id')
                ->references('id')
                ->on('patient_match_candidates')
                ->onDelete('set null');

            $table->foreign('patient_customer_link_id')
                ->references('id')
                ->on('patient_customer_links')
                ->onDelete('set null');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
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

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
        Schema::create('patient_customer_links', function (Blueprint $table) {
            $table->id();

            // Link
            $table->unsignedBigInteger('patient_id');
            $table->unsignedInteger('customer_id');

            // Link metadata
            $table->enum('link_type', ['exact_match', 'fuzzy_match', 'manual_link']);
            $table->decimal('confidence_score', 5, 4)->nullable()->comment('Score at time of approval');
            $table->unsignedBigInteger('match_candidate_id')->nullable()->comment('Reference to patient_match_candidates');

            // Audit (Admin only)
            $table->unsignedBigInteger('linked_by')->nullable()->comment('Admin user who approved/created link');
            $table->timestamp('linked_at')->useCurrent();
            $table->text('notes')->nullable();

            // Timestamps
            $table->timestamps();

            // Constraints
            $table->unique(['patient_id', 'customer_id'], 'uk_patient_customer');
            $table->index('customer_id');
            $table->index('link_type');

            // Foreign keys
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('match_candidate_id')->references('id')->on('patient_match_candidates')->onDelete('set null');
            $table->foreign('linked_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_customer_links');
    }
};

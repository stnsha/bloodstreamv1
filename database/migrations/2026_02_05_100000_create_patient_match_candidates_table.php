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
        Schema::create('patient_match_candidates', function (Blueprint $table) {
            $table->id();

            // Source data (from Lab/MyHealth)
            $table->unsignedBigInteger('patient_id');
            $table->string('source_ic', 50);
            $table->string('source_ic_normalized', 50)->nullable();
            $table->string('source_refid', 50)->nullable();
            $table->string('source_refid_normalized', 50)->nullable();
            $table->string('source_name', 255)->nullable();
            $table->string('source_dob', 20)->nullable();
            $table->string('source_gender', 10)->nullable();
            $table->string('source_lab_code', 20)->nullable();

            // Candidate data (from Octopus)
            $table->unsignedInteger('candidate_customer_id');
            $table->string('candidate_ic', 50);
            $table->string('candidate_name', 255)->nullable();
            $table->string('candidate_dob', 20)->nullable();
            $table->string('candidate_gender', 10)->nullable();
            $table->string('candidate_refid', 50)->nullable();

            // Matching scores (0.0000 to 1.0000)
            $table->decimal('ic_score', 5, 4)->default(0);
            $table->string('ic_match_method', 50)->nullable()->comment('exact, normalized, fuzzy_levenshtein, dob_prefix');
            $table->decimal('refid_score', 5, 4)->default(0);
            $table->string('refid_match_method', 50)->nullable();
            $table->decimal('dob_score', 5, 4)->default(0);
            $table->decimal('gender_score', 5, 4)->default(0);

            // Overall confidence
            $table->decimal('confidence_score', 5, 4)->default(0);

            // Review status (NO auto_matched option - all require review)
            $table->enum('status', ['pending_review', 'approved', 'rejected'])->default('pending_review');
            $table->unsignedBigInteger('reviewed_by')->nullable()->comment('Admin user who reviewed');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            // Metadata
            $table->string('match_algorithm_version', 20)->default('1.0');
            $table->timestamps();

            // Indexes
            $table->index('patient_id');
            $table->index('candidate_customer_id');
            $table->index('status');
            $table->index('confidence_score');
            $table->index('source_ic');
            $table->index('source_lab_code');

            // Foreign keys
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_match_candidates');
    }
};

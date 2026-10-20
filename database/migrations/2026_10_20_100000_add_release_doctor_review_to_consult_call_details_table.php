<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "Release Doctor Review?" answer last saved on the consult-call edit
     * screen, so the form can show it again after a reload. null = not
     * answered, false = No, true = Yes. The release itself is still tracked on
     * test_results.ai_review_released_at; this column only records the answer.
     */
    public function up(): void
    {
        Schema::table('consult_call_details', function (Blueprint $table) {
            $table->boolean('release_doctor_review')->nullable()->after('is_invoice_synced');
        });
    }

    public function down(): void
    {
        Schema::table('consult_call_details', function (Blueprint $table) {
            $table->dropColumn('release_doctor_review');
        });
    }
};

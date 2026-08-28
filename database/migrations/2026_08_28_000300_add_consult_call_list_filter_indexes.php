<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for the consult-call list screen filters (5k+ rows). The list
     * filters on the latest detail's process_status / consulted_by / action /
     * is_draft and on follow-up reminder state via correlated subqueries and
     * whereHas -- none of those columns were indexed.
     */
    public function up(): void
    {
        Schema::table('consult_call_details', function (Blueprint $table) {
            // Covers the "latest non-deleted detail per consult call" subqueries.
            $table->index(['consult_call_id', 'deleted_at', 'id'], 'ccd_call_deleted_id_idx');
            $table->index('process_status', 'ccd_process_status_idx');
            $table->index('consulted_by', 'ccd_consulted_by_idx');
            $table->index('action', 'ccd_action_idx');
        });

        Schema::table('consult_call_follow_ups', function (Blueprint $table) {
            $table->index(['consult_call_id', 'followup_reminder'], 'ccf_call_reminder_idx');
            // Covers the "latest non-deleted follow-up per consult call" subquery.
            $table->index(['consult_call_id', 'deleted_at', 'id'], 'ccf_call_deleted_id_idx');
        });

        Schema::table('consult_calls', function (Blueprint $table) {
            $table->index('enrollment_date', 'cc_enrollment_date_idx');
            $table->index('consent_call_status', 'cc_consent_status_idx');
            $table->index('scheduled_status', 'cc_scheduled_status_idx');
            $table->index('enrollment_type', 'cc_enrollment_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('consult_call_details', function (Blueprint $table) {
            $table->dropIndex('ccd_call_deleted_id_idx');
            $table->dropIndex('ccd_process_status_idx');
            $table->dropIndex('ccd_consulted_by_idx');
            $table->dropIndex('ccd_action_idx');
        });

        Schema::table('consult_call_follow_ups', function (Blueprint $table) {
            $table->dropIndex('ccf_call_reminder_idx');
            $table->dropIndex('ccf_call_deleted_id_idx');
        });

        Schema::table('consult_calls', function (Blueprint $table) {
            $table->dropIndex('cc_enrollment_date_idx');
            $table->dropIndex('cc_consent_status_idx');
            $table->dropIndex('cc_scheduled_status_idx');
            $table->dropIndex('cc_enrollment_type_idx');
        });
    }
};

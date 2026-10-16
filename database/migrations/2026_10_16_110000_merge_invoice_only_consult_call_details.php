<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Merge stray invoice-only consult_call_details rows back into the completed
 * consultation they belong to.
 *
 * Cause: before the consultcall js/edit.js fix of 2026-10-16, a Customer Support save
 * of the Add On Recommendation invoice fields (invoice_id / invoice_status /
 * is_invoice_synced) inserted a new, empty detail whenever the latest detail had been
 * completed by a doctor other than the current user. The consult call list reads the
 * latest detail, so Consulted By / Action / Consult Date showed "-" for those calls
 * (e.g. consult call 8508: completed detail 8644, stray invoice-only detail 9090).
 *
 * A stray row is a live detail carrying an invoice but no consultation data, whose
 * previous live detail is a completed consultation (consult_status = 1) with no
 * invoice of its own. For each one:
 *   - invoice_id / invoice_status / is_invoice_synced are copied onto the previous detail
 *   - consult_call_add_ons, consult_call_add_on_results and consult_call_follow_ups rows
 *     pointing at the stray detail are re-pointed to the previous detail
 *   - the stray detail is soft-deleted
 *
 * Each merged pair is written to the application log for manual reversal.
 */
return new class extends Migration
{
    private const DETAIL_REFERENCING_TABLES = [
        'consult_call_add_ons',
        'consult_call_add_on_results',
        'consult_call_follow_ups',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $pairs = DB::select("
            SELECT d.id AS stray_detail_id,
                   p.id AS target_detail_id,
                   d.consult_call_id,
                   d.invoice_id,
                   d.invoice_status,
                   d.is_invoice_synced
            FROM consult_call_details d
            JOIN consult_call_details p
              ON p.consult_call_id = d.consult_call_id
             AND p.deleted_at IS NULL
             AND p.id = (SELECT MAX(x.id) FROM consult_call_details x
                         WHERE x.consult_call_id = d.consult_call_id
                           AND x.id < d.id
                           AND x.deleted_at IS NULL)
            WHERE d.deleted_at IS NULL
              AND d.invoice_id IS NOT NULL
              AND d.consulted_by IS NULL
              AND d.consult_date IS NULL
              AND d.documentation IS NULL
              AND d.diagnosis IS NULL
              AND d.treatment_plan IS NULL
              AND d.action IS NULL
              AND p.consult_status = 1
              AND p.invoice_id IS NULL
        ");

        DB::transaction(function () use ($pairs) {
            $now = now();

            foreach ($pairs as $pair) {
                DB::table('consult_call_details')
                    ->where('id', $pair->target_detail_id)
                    ->update([
                        'invoice_id' => $pair->invoice_id,
                        'invoice_status' => $pair->invoice_status,
                        'is_invoice_synced' => $pair->is_invoice_synced,
                        'updated_at' => $now,
                    ]);

                foreach (self::DETAIL_REFERENCING_TABLES as $table) {
                    DB::table($table)
                        ->where('consult_call_detail_id', $pair->stray_detail_id)
                        ->update(['consult_call_detail_id' => $pair->target_detail_id]);
                }

                DB::table('consult_call_details')
                    ->where('id', $pair->stray_detail_id)
                    ->update(['deleted_at' => $now]);

                Log::info('Merged invoice-only consult_call_detail', (array) $pair);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally a no-op: once merged, the copied invoice values cannot be told
     * apart from values entered on the form afterwards. Use the logged pairs to
     * restore a stray detail (deleted_at = NULL) by hand if ever required.
     */
    public function down(): void
    {
        //
    }
};

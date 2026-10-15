<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill consult_calls.call_answered (1 = Yes, 0 = No, 2 = None / no call made)
 * for consult calls recorded before the Answered Call field existed.
 *
 * Only rows still NULL are touched, so values already entered on the form are kept.
 * Pending consent (0) is left NULL -- no call outcome has been recorded yet.
 *
 * Rules, in order:
 *   - Consent Obtained (1)                                   -> Yes
 *   - Consent Refused (2) / Others (3):
 *       remark shows no call was made (overdue report, exclusion
 *       criteria, corporate not eligible, case discussed with Dr,
 *       wrong report, trigger value, retake test, bare "staff") -> None
 *       remark shows the call was not answered (no answer / no
 *       response / no reply / did not pick up / unreachable /
 *       wrong or invalid number)                              -> No
 *       any other remark (walk-in, on medication, follow-up,
 *       not keen, no-show, own doctor, ...)                   -> Yes
 *       no remark, reason Unreachable / Foreign Number         -> No
 *       no remark, any other reason                            -> Yes
 */
return new class extends Migration
{
    private const NO_CALL_MADE_PATTERN = 'overdue|exclusion criteria|cross-border|corporate account|hq-corporate'
        . '|case discussed with dr|clinical condition isolated|not eligible|wrong report|report belongs to'
        . '|trigger value|retake blood test|sample tube';

    private const BARE_STAFF_PATTERN = '^(staf|staff|staff alpro)$';

    private const NOT_ANSWERED_PATTERN = 'no answer|no response|no responses|no reply|no rep|not reply'
        . "|didn.?t reply|don.?t reply|dont reply|did not pick|not pick up|unreachable|not reachable"
        . '|unable to reach|wrong number|invalid number|no valid number|number incomplete|f/uo no response';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $remark = "LOWER(TRIM(COALESCE(final_remarks, '')))";

        DB::update("
            UPDATE consult_calls
            SET call_answered = CASE
                WHEN consent_call_status = 1 THEN 1
                WHEN $remark REGEXP ? OR $remark REGEXP ? THEN 2
                WHEN $remark REGEXP ? THEN 0
                WHEN $remark <> '' THEN 1
                WHEN reason IN ('Unreachable', 'Foreign Number') THEN 0
                ELSE 1
            END
            WHERE call_answered IS NULL
              AND consent_call_status IN (1, 2, 3)
        ", [self::NO_CALL_MADE_PATTERN, self::BARE_STAFF_PATTERN, self::NOT_ANSWERED_PATTERN]);
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally a no-op: backfilled values cannot be told apart from values
     * entered on the form afterwards, so clearing them could erase real data.
     * Rolling back 2026_10_15_120000 (dropping the column) removes everything.
     */
    public function down(): void
    {
        //
    }
};

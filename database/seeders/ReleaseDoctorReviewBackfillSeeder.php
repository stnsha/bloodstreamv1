<?php

namespace Database\Seeders;

use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pre-fill consult_call_details.release_doctor_review = Yes on every detail of a
 * consult call whose held AI review has already been released (via any of its
 * details' test results), so the edit screen shows the answer for releases made
 * before the column existed.
 *
 * Safe to re-run: only unanswered (null) rows are touched, so answers saved
 * through the edit screen are never overwritten.
 *
 * Run: php artisan db:seed --class=ReleaseDoctorReviewBackfillSeeder
 */
class ReleaseDoctorReviewBackfillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info('ReleaseDoctorReviewBackfillSeeder: Starting backfill');

        $releasedConsultCallIds = DB::table('consult_call_details')
            ->join('test_results', 'test_results.id', '=', 'consult_call_details.test_result_id')
            ->whereNotNull('test_results.ai_review_held_at')
            ->whereNotNull('test_results.ai_review_released_at')
            ->distinct()
            ->pluck('consult_call_details.consult_call_id');

        try {
            DB::beginTransaction();

            $updated = 0;

            foreach ($releasedConsultCallIds->chunk(500) as $chunk) {
                $updated += DB::table('consult_call_details')
                    ->whereNull('release_doctor_review')
                    ->whereIn('consult_call_id', $chunk->all())
                    ->update(['release_doctor_review' => true]);
            }

            DB::commit();

            Log::info('ReleaseDoctorReviewBackfillSeeder: Backfill completed', [
                'consult_calls' => $releasedConsultCallIds->count(),
                'details_updated' => $updated,
            ]);

            $this->command?->info("Consult calls: {$releasedConsultCallIds->count()}, details updated: {$updated}");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('ReleaseDoctorReviewBackfillSeeder: Backfill failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

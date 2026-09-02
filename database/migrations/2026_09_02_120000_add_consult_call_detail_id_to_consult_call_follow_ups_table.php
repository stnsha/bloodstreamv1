<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('consult_call_follow_ups', function (Blueprint $table) {
            $table->unsignedBigInteger('consult_call_detail_id')
                ->nullable()
                ->after('consult_call_id');

            $table->index('consult_call_detail_id');

            $table->foreign('consult_call_detail_id')
                ->references('id')
                ->on('consult_call_details')
                ->nullOnDelete();
        });

        $this->backfillConsultCallDetailId();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consult_call_follow_ups', function (Blueprint $table) {
            $table->dropForeign(['consult_call_detail_id']);
            $table->dropIndex(['consult_call_detail_id']);
            $table->dropColumn('consult_call_detail_id');
        });
    }

    /**
     * consult_call_follow_ups has never carried a link to the specific
     * consultation it belongs to. The closest available signal is time
     * proximity: a follow-up's created_at falls on (or very near) its
     * consultation's consult_date.
     *
     * For each consult call, pair every follow-up to the detail whose
     * consult_date is nearest the follow-up's created_at. Smallest global
     * distance is assigned first and each detail / follow-up is used at most
     * once. Follow-ups left over (more follow-ups than dated details) fall back
     * to positional order on the remainder.
     */
    private function backfillConsultCallDetailId(): void
    {
        $followUpsByCall = DB::table('consult_call_follow_ups')
            ->whereNull('deleted_at')
            ->whereNull('consult_call_detail_id')
            ->orderBy('id')
            ->get(['id', 'consult_call_id', 'created_at']);

        $grouped = $followUpsByCall->groupBy('consult_call_id');

        foreach ($grouped as $consultCallId => $followUps) {
            $details = DB::table('consult_call_details')
                ->where('consult_call_id', $consultCallId)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get(['id', 'consult_date']);

            if ($details->isEmpty()) {
                continue;
            }

            $detailList = $details->values();
            $followUpList = $followUps->values();

            $candidates = [];
            foreach ($detailList as $di => $detail) {
                if (empty($detail->consult_date)) {
                    continue;
                }
                $detailTs = strtotime($detail->consult_date);
                foreach ($followUpList as $fi => $followUp) {
                    if (empty($followUp->created_at)) {
                        continue;
                    }
                    $candidates[] = [
                        'di'   => $di,
                        'fi'   => $fi,
                        'dist' => abs($detailTs - strtotime($followUp->created_at)),
                    ];
                }
            }

            usort($candidates, function ($a, $b) {
                return $a['dist'] <=> $b['dist'];
            });

            $usedDetail = [];
            $usedFollowUp = [];
            $assignment = [];

            foreach ($candidates as $candidate) {
                if (isset($assignment[$candidate['fi']]) || isset($usedDetail[$candidate['di']])) {
                    continue;
                }
                $assignment[$candidate['fi']] = $detailList[$candidate['di']]->id;
                $usedDetail[$candidate['di']] = true;
                $usedFollowUp[$candidate['fi']] = true;
            }

            // Positional fallback for follow-ups still unassigned.
            $nextDi = 0;
            foreach ($followUpList as $fi => $followUp) {
                if (isset($assignment[$fi])) {
                    continue;
                }
                while ($nextDi < $detailList->count() && isset($usedDetail[$nextDi])) {
                    $nextDi++;
                }
                if ($nextDi < $detailList->count()) {
                    $assignment[$fi] = $detailList[$nextDi]->id;
                    $usedDetail[$nextDi] = true;
                } else {
                    // More follow-ups than details: attach to the last detail.
                    $assignment[$fi] = $detailList->last()->id;
                }
            }

            foreach ($followUpList as $fi => $followUp) {
                if (!isset($assignment[$fi])) {
                    continue;
                }
                DB::table('consult_call_follow_ups')
                    ->where('id', $followUp->id)
                    ->update(['consult_call_detail_id' => $assignment[$fi]]);
            }
        }
    }
};

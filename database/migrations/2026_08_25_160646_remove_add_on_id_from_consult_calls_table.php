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
        // Superseded by the consult_call_add_ons pivot table (one consult call can now
        // have many recommended add-ons) -- backfill existing single values before dropping.
        $now = now();
        $rows = DB::table('consult_calls')
            ->whereNotNull('add_on_id')
            ->get(['id', 'add_on_id']);

        foreach ($rows as $row) {
            DB::table('consult_call_add_ons')->insertOrIgnore([
                'consult_call_id' => $row->id,
                'add_on_id' => $row->add_on_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('consult_calls', function (Blueprint $table) {
            $table->dropForeign(['add_on_id']);
            $table->dropColumn('add_on_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consult_calls', function (Blueprint $table) {
            $table->unsignedBigInteger('add_on_id')->nullable()->after('reason');
            $table->foreign('add_on_id')->references('id')->on('add_ons')->onDelete('set null');
        });

        // Best-effort restore: takes the first pivot row per consult call since the
        // column can only hold a single value again.
        $firstPerConsultCall = DB::table('consult_call_add_ons')
            ->select('consult_call_id', DB::raw('MIN(add_on_id) as add_on_id'))
            ->groupBy('consult_call_id')
            ->get();

        foreach ($firstPerConsultCall as $row) {
            DB::table('consult_calls')
                ->where('id', $row->consult_call_id)
                ->update(['add_on_id' => $row->add_on_id]);
        }
    }
};

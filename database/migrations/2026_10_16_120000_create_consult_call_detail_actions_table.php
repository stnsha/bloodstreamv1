<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A consultation detail can now have several "Action" options (action_master)
     * selected. Each selection is one row here, holding the action_master FK.
     * Existing single selections (consult_call_details.action_master_id) are copied
     * over; that column is kept, mirrored to the first selected action, until a
     * later cleanup.
     */
    public function up(): void
    {
        Schema::create('consult_call_detail_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consult_call_detail_id');
            // Options are deactivated, never deleted, so no cascade on action_master.
            $table->foreignId('action_master_id')->constrained('action_master');
            $table->timestamps();

            $table->foreign('consult_call_detail_id')
                ->references('id')->on('consult_call_details')
                ->onDelete('cascade');
            $table->unique(['consult_call_detail_id', 'action_master_id'], 'ccd_actions_detail_action_unique');
        });

        DB::statement('
            INSERT INTO consult_call_detail_actions (consult_call_detail_id, action_master_id, created_at, updated_at)
            SELECT id, action_master_id, NOW(), NOW()
            FROM consult_call_details
            WHERE action_master_id IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consult_call_detail_actions');
    }
};

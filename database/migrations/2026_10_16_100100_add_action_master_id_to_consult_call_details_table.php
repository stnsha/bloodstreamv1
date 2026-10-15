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
        Schema::table('consult_call_details', function (Blueprint $table) {
            // Selected "Action" option (action_master). Options are deactivated, never
            // deleted, so existing selections keep their reference.
            $table->foreignId('action_master_id')
                ->nullable()
                ->after('action')
                ->constrained('action_master');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consult_call_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('action_master_id');
        });
    }
};

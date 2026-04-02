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
        Schema::table('panel_items', function (Blueprint $table) {
            $table->string('chi_character')->nullable()->after('name');
        });

        // Copy existing chi_character values from master_panel_items to panel_items
        DB::statement("
            UPDATE panel_items pi
            JOIN master_panel_items mpi ON pi.master_panel_item_id = mpi.id
            SET pi.chi_character = mpi.chi_character
            WHERE mpi.chi_character IS NOT NULL
              AND pi.deleted_at IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('panel_items', function (Blueprint $table) {
            $table->dropColumn('chi_character');
        });
    }
};

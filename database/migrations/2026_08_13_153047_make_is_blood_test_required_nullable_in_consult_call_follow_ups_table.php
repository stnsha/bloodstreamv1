<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE consult_call_follow_ups MODIFY is_blood_test_required TINYINT(1) NULL DEFAULT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE consult_call_follow_ups MODIFY is_blood_test_required TINYINT(1) NOT NULL DEFAULT 0");
    }
};

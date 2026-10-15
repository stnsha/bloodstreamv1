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
        Schema::table('consult_calls', function (Blueprint $table) {
            // null = not recorded, 0 = No, 1 = Yes, 2 = None (no call made)
            $table->tinyInteger('call_answered')->nullable()->after('enrollment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consult_calls', function (Blueprint $table) {
            $table->dropColumn('call_answered');
        });
    }
};

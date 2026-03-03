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
        Schema::table('consult_call_follow_ups', function (Blueprint $table) {
            $table->dropColumn('my_referral_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consult_call_follow_ups', function (Blueprint $table) {
            $table->integer('my_referral_status')->default(0)->after('referral_to');
        });
    }
};

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
            $table->string('reason')->nullable()->after('consent_call_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consult_calls', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};

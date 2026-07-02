<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incomplete_test_results', function (Blueprint $table) {
            $table->string('reason', 32)->nullable()->after('actual_panel_count');
        });
    }

    public function down(): void
    {
        Schema::table('incomplete_test_results', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incomplete_test_results', function (Blueprint $table) {
            $table->text('missing_details')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('incomplete_test_results', function (Blueprint $table) {
            $table->dropColumn('missing_details');
        });
    }
};

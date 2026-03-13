<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consult_call_details', function (Blueprint $table) {
            $table->longText('documentation')->nullable()->after('test_result_id');
        });
    }

    public function down(): void
    {
        Schema::table('consult_call_details', function (Blueprint $table) {
            $table->dropColumn('documentation');
        });
    }
};

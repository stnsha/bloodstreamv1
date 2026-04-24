<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consult_call_details', function (Blueprint $table) {
            $table->tinyInteger('is_draft')->default(0)->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('consult_call_details', function (Blueprint $table) {
            $table->dropColumn('is_draft');
        });
    }
};

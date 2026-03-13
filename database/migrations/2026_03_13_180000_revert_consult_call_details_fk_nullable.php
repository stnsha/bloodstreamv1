<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consult_call_details', function (Blueprint $table) {
            $table->unsignedTinyInteger('clinical_condition_id')->nullable(false)->change();
            $table->unsignedBigInteger('test_result_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('consult_call_details', function (Blueprint $table) {
            $table->unsignedTinyInteger('clinical_condition_id')->nullable()->change();
            $table->unsignedBigInteger('test_result_id')->nullable()->change();
        });
    }
};

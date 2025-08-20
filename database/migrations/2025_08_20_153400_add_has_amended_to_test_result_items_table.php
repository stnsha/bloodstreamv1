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
    public function up()
    {
        Schema::table('test_result_items', function (Blueprint $table) {
            $table->boolean('hasAmended')->default(false)->after('value');
        });

        DB::table('test_result_items')->update(['hasAmended' => false]);
    }
    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('test_result_items', function (Blueprint $table) {
            $table->dropColumn('hasAmended');
        });
    }
};
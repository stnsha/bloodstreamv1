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
            $table->unsignedBigInteger('add_on_id')->nullable()->after('reason');
            $table->foreign('add_on_id')->references('id')->on('add_ons')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consult_calls', function (Blueprint $table) {
            $table->dropForeign(['add_on_id']);
            $table->dropColumn('add_on_id');
        });
    }
};

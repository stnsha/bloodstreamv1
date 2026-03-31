<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consult_calls', function (Blueprint $table) {
            $table->integer('scheduled_status')->nullable()->default(null)->change();
            $table->integer('mode_of_consultation')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('consult_calls', function (Blueprint $table) {
            $table->integer('scheduled_status')->nullable(false)->default(0)->change();
            $table->integer('mode_of_consultation')->nullable(false)->default(0)->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * cluster_scope scopes a condition to a consult-call flow:
     * 1 = consult call only, 2 = cluster (ARR) only, 3 = both.
     * Existing conditions default to 1 (consult call only).
     */
    public function up(): void
    {
        Schema::table('clinical_conditions', function (Blueprint $table) {
            $table->unsignedTinyInteger('cluster_scope')->default(1)->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinical_conditions', function (Blueprint $table) {
            $table->dropColumn(['cluster_scope']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The cluster clinical condition that enrolled the patient into ARR.
     * Nullable so existing rows stay valid; set to null if the condition is deleted
     * so the enrollment itself is kept.
     */
    public function up(): void
    {
        Schema::table('arr_enrollments', function (Blueprint $table) {
            $table->unsignedTinyInteger('clinical_condition_id')->nullable()->after('outlet_id');

            $table->foreign('clinical_condition_id')->references('id')->on('clinical_conditions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('arr_enrollments', function (Blueprint $table) {
            $table->dropForeign(['clinical_condition_id']);
            $table->dropColumn(['clinical_condition_id']);
        });
    }
};

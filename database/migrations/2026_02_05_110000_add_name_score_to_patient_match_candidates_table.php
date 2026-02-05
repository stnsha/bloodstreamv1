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
        Schema::table('patient_match_candidates', function (Blueprint $table) {
            $table->decimal('name_score', 5, 4)->default(0)->after('refid_match_method');
            $table->string('name_match_method', 50)->nullable()->after('name_score')
                ->comment('exact, fuzzy_levenshtein, partial_contains, token_match');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_match_candidates', function (Blueprint $table) {
            $table->dropColumn(['name_score', 'name_match_method']);
        });
    }
};

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
        Schema::table('ai_errors', function (Blueprint $table) {
            $table->string('processing_status', 50)
                ->nullable()
                ->after('test_result_id')
                ->index('idx_ai_errors_processing_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('ai_errors', function (Blueprint $table) {
            $table->dropIndex('idx_ai_errors_processing_status');
            $table->dropColumn('processing_status');
        });
        Schema::enableForeignKeyConstraints();
    }
};

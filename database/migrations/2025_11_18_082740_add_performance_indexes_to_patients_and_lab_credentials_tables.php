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
        // Add index to patients.icno for faster lookups
        // This fixes slow query: select * from `patients` where (`icno` = ?) and `patients`.`deleted_at` is null
        Schema::table('patients', function (Blueprint $table) {
            $table->index('icno');
        });

        // Add composite index to lab_credentials for faster lookups with soft deletes
        // This fixes slow query: select * from `lab_credentials` where `id` = ? and `lab_credentials`.`deleted_at` is null
        Schema::table('lab_credentials', function (Blueprint $table) {
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['icno']);
        });

        Schema::table('lab_credentials', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
        });

        Schema::enableForeignKeyConstraints();
    }
};

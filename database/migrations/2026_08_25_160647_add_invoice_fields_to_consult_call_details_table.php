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
        Schema::table('consult_call_details', function (Blueprint $table) {
            $table->string('invoice_id')->nullable()->after('rx_issued');
            $table->unsignedTinyInteger('invoice_status')->nullable()->after('invoice_id'); // 1 - confirmed, 2 - completed
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consult_call_details', function (Blueprint $table) {
            $table->dropColumn(['invoice_id', 'invoice_status']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each recommended add-on records the invoice that bought it.
     *
     * One invoice (one ODB blood_test_sales.id) can contain several recommended
     * add-on item codes, so several rows can share the same blood_test_sales_id.
     * The lab returns that id inside test_results.ref_id as <lab code><id>.
     */
    public function up(): void
    {
        Schema::table('consult_call_add_ons', function (Blueprint $table) {
            $table->unsignedBigInteger('consult_call_detail_id')->nullable()->after('add_on_id');
            $table->string('invoice_id')->nullable()->after('consult_call_detail_id');
            $table->unsignedBigInteger('blood_test_sales_id')->nullable()->after('invoice_id');
            $table->unsignedTinyInteger('invoice_status')->nullable()->after('blood_test_sales_id'); // 1 - confirmed, 2 - completed

            $table->index('blood_test_sales_id');
            $table->foreign('consult_call_detail_id')->references('id')->on('consult_call_details')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consult_call_add_ons', function (Blueprint $table) {
            $table->dropForeign(['consult_call_detail_id']);
            $table->dropIndex(['blood_test_sales_id']);
            $table->dropColumn(['consult_call_detail_id', 'invoice_id', 'blood_test_sales_id', 'invoice_status']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records the blood_test_sales invoice number that caused this consult-call
     * detail to be created as a continuation of an earlier consult call (add-on
     * blood test flow). Used to enforce one continuation per invoice: a later
     * delivery whose invoice already appears here is not linked again.
     */
    public function up(): void
    {
        Schema::table('consult_call_details', function (Blueprint $table) {
            $table->string('linked_via_invoice')->nullable()->after('is_invoice_synced');
            $table->index('linked_via_invoice');
        });
    }

    public function down(): void
    {
        Schema::table('consult_call_details', function (Blueprint $table) {
            $table->dropIndex(['linked_via_invoice']);
            $table->dropColumn('linked_via_invoice');
        });
    }
};

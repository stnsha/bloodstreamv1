<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the Add-On "Invoice ID" on this consult-call detail has been
     * synced into the ODB blood_test_sales table (via the Xilnex pull the
     * consult-call edit screen triggers on save). false means the invoice was
     * entered but no matching blood_test_sales row exists yet, so the edit
     * screen shows a "not synced" marker and retries the sync on the next save.
     */
    public function up(): void
    {
        Schema::table('consult_call_details', function (Blueprint $table) {
            $table->boolean('is_invoice_synced')->default(false)->after('invoice_status');
        });
    }

    public function down(): void
    {
        Schema::table('consult_call_details', function (Blueprint $table) {
            $table->dropColumn('is_invoice_synced');
        });
    }
};

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
        Schema::create('reference_ranges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panel_item_id');
            $table->string('value');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('panel_item_id')->references('id')->on('panel_items')->onDelete('cascade');
        });

        Schema::table('test_result_items', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['panel_item_id']);

            // Then drop the column
            $table->dropColumn('panel_item_id');

            // Add new column
            $table->unsignedBigInteger('reference_range_id')->after('test_result_id');;

            // Add new foreign key constraint
            $table->foreign('reference_range_id')->references('id')->on('reference_ranges')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_result_items', function (Blueprint $table) {
            $table->dropForeign(['reference_range_id']);
            $table->dropColumn('reference_range_id');
        });

        Schema::dropIfExists('reference_ranges');

        Schema::table('test_result_items', function (Blueprint $table) {
            $table->unsignedBigInteger('panel_item_id');
            $table->foreign('panel_item_id')->references('id')->on('panel_items')->onDelete('cascade');
        });
    }
};

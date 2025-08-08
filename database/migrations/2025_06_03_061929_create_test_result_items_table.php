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
        Schema::create('test_result_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('test_result_id');
            $table->unsignedBigInteger('panel_item_id');
            $table->unsignedBigInteger('reference_range_id')->nullable();
            $table->string('value')->nullable();
            $table->string('flag')->nullable();
            $table->longText('test_notes')->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('test_result_id')->references('id')->on('test_results')->onDelete('cascade');
            $table->foreign('reference_range_id')->references('id')->on('reference_ranges')->onDelete('cascade');
            $table->foreign('panel_item_id')->references('id')->on('panel_items')->onDelete('cascade');

            $table->index(['reference_range_id', 'panel_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('test_result_items');
    }
};

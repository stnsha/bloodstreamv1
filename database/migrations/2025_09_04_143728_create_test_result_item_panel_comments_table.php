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
        Schema::create('test_result_item_panel_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('test_result_item_id');
            $table->unsignedBigInteger('panel_comment_id');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('test_result_item_id')->references('id')->on('test_result_items')->onDelete('cascade');
            $table->foreign('panel_comment_id')->references('id')->on('panel_comments')->onDelete('cascade');

            // Composite unique index to prevent duplicate relationships
            $table->unique(['test_result_item_id', 'panel_comment_id'], 'test_result_item_panel_comment_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_result_item_panel_comments');
    }
};
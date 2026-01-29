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
        Schema::create('panel_merge_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panel_merge_log_id');
            $table->string('action'); // merged, deleted, updated, created, repointed
            $table->string('entity_type'); // MasterPanel, MasterPanelItem, Panel, PanelItem, etc.
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_name')->nullable();
            $table->string('entity_unit')->nullable();
            $table->unsignedBigInteger('target_id')->nullable(); // For merges: the survivor ID
            $table->string('target_name')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('panel_merge_log_id')
                ->references('id')
                ->on('panel_merge_logs')
                ->onDelete('cascade');

            $table->index(['panel_merge_log_id', 'action']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('panel_merge_details');
    }
};

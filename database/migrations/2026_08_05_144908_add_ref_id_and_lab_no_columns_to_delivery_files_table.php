<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `lab_no` already exists on this table as a MySQL generated column
     * (`GENERATED ALWAYS AS (json_unquote(json_extract(json_content,
     * '$.Orders[0].FillerOrderNumber'))) STORED`) that was added directly to
     * the database outside of any migration in this repo. Its JSON path is
     * wrong for Innoquest payloads -- FillerOrderNumber lives at
     * Orders[x].Observations[y].FillerOrderNumber, not Orders[0] directly --
     * so it silently evaluates to null on every real row. It is dropped here
     * and replaced, alongside a new `ref_id` column, with plain columns that
     * the application sets explicitly from the already-parsed
     * PlacerOrderNumber (ref_id) / FillerOrderNumber (lab_no) values.
     */
    public function up(): void
    {
        if (Schema::hasColumn('delivery_files', 'lab_no')) {
            Schema::table('delivery_files', function (Blueprint $table) {
                $table->dropIndex('idx_lab_no');
            });

            DB::statement('ALTER TABLE delivery_files DROP COLUMN lab_no');
        }

        Schema::table('delivery_files', function (Blueprint $table) {
            $table->string('ref_id')->nullable()->after('batch_id');
            $table->string('lab_no')->nullable()->after('ref_id');

            $table->index('ref_id');
            $table->index('lab_no');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Only removes the plain columns added here. The original generated
     * `lab_no` column was untracked drift, not something a prior migration
     * created, so it is not recreated.
     */
    public function down(): void
    {
        Schema::table('delivery_files', function (Blueprint $table) {
            $table->dropIndex(['ref_id']);
            $table->dropIndex(['lab_no']);
            $table->dropColumn(['ref_id', 'lab_no']);
        });
    }
};

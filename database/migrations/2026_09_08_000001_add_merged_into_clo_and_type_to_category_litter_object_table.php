<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Completes the retirement record.
 *
 * `litter_objects.merged_into_id` records only the survivor object. An approved mapping is a
 * triple — survivor object, survivor category, survivor type — and a retirement can span several
 * categories with a different survivor pairing in each (`straws` retired into `softdrinks/straw`
 * and `marine/straw`). The mapping therefore belongs on the source pivot, not the object.
 *
 * With these two columns a tombstone pivot answers "where does this land now?" directly, so stale
 * clients and saved quick tags keep the approved subtype and land in the approved category
 * instead of searching the original category and losing both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_litter_object', function (Blueprint $table) {
            $table->unsignedBigInteger('merged_into_clo_id')->nullable()->after('litter_object_id');
            $table->unsignedInteger('merged_into_type_id')->nullable()->after('merged_into_clo_id');

            $table->foreign('merged_into_clo_id')
                ->references('id')->on('category_litter_object')
                ->nullOnDelete();
            $table->foreign('merged_into_type_id')
                ->references('id')->on('litter_object_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('category_litter_object', function (Blueprint $table) {
            $table->dropForeign(['merged_into_clo_id']);
            $table->dropForeign(['merged_into_type_id']);
            $table->dropColumn(['merged_into_clo_id', 'merged_into_type_id']);
        });
    }
};

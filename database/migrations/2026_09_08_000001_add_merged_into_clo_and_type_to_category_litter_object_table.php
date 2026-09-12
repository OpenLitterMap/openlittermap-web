<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Store the replacement CLO ID and optional type on the old CLO row.
 * - merged_into_clo_id identifies both the replacement category and object.
 * - merged_into_type_id keeps the type, e.g. beer_can → can with type beer.
 * - A category move can retire the old CLO while keeping the object active.
 * - Requests using the old CLO ID can follow the recorded replacement.
 * - Adding these columns does not move any photo tags or quick tags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_litter_object', function (Blueprint $table) {
            $table->unsignedBigInteger('merged_into_clo_id')->nullable()->after('litter_object_id');
            $table->unsignedBigInteger('merged_into_type_id')->nullable()->after('merged_into_clo_id');

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

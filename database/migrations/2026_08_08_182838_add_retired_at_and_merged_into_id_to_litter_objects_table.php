<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes retirement an explicit fact rather than an accident of a missing pivot row.
 *
 * Before this, an object was invisible to the tag picker only because
 * GetTagsController::getAllTags() filters on whereHas('categories') — so a pivotless object
 * happened to be unselectable. The retirement process deliberately creates pivots, so that
 * side effect stops holding. retired_at lets the picker be filtered explicitly, gives the
 * verifier something to assert, and lets firstOrCreate paths refuse to resurrect a retired key.
 *
 * @see readme/PostTagMigrationClean.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('litter_objects', function (Blueprint $table) {
            $table->timestamp('retired_at')->nullable()->after('crowdsourced');
            $table->unsignedBigInteger('merged_into_id')->nullable()->after('retired_at');

            $table->index('retired_at');
            $table->foreign('merged_into_id')->references('id')->on('litter_objects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('litter_objects', function (Blueprint $table) {
            $table->dropForeign(['merged_into_id']);
            $table->dropIndex(['retired_at']);
            $table->dropColumn(['retired_at', 'merged_into_id']);
        });
    }
};

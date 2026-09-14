<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Add retired_at to mark an object as retired and merged_into_id to identify its replacement.
 * - Example: plasticBags records plastic_bag's object ID as its replacement.
 * - The tag picker can exclude retired objects while requests using old IDs can resolve them.
 * - Adding these columns does not retire or move any tags.
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

            $table->foreign('merged_into_id')->references('id')->on('litter_objects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('litter_objects', function (Blueprint $table) {
            $table->dropForeign(['merged_into_id']);
            $table->dropColumn(['retired_at', 'merged_into_id']);
        });
    }
};

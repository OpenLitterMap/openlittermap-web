<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix processed_xp column type: INT → INT UNSIGNED.
 *
 * The ensureProcessingColumns() fallback originally created this as TINYINT(1),
 * which overflows at 255. The proper migration created it as INT (signed).
 * This migration changes it to INT UNSIGNED for safety (XP is always >= 0).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->unsignedInteger('processed_xp')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->integer('processed_xp')->nullable()->change();
        });
    }
};

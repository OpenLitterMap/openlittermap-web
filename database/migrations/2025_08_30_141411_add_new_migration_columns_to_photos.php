<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add processing tracking fields to photos table
 * These ensure metrics processing is idempotent and reversible
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            // Add fingerprint for idempotency check (16 chars from xxh128 hash)
            $table->char('processed_fp', 16)->nullable()->after('processed_at')
                ->comment('Fingerprint of processed tags for idempotency');

            // Add processed tags cache for delta calculation
            $table->text('processed_tags')->nullable()->after('processed_fp')
                ->comment('JSON of tags structure at last processing');

            // Add processed XP for correct delta calculation
            $table->unsignedInteger('processed_xp')->nullable()->after('processed_tags')
                ->comment('XP value at last processing for delta calculation');

            // Add index for finding unprocessed photos efficiently
            $table->index('processed_fp', 'photos_processed_fp_index');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex('photos_processed_fp_index');
            $table->dropColumn(['processed_fp', 'processed_tags', 'processed_xp']);
        });
    }
};

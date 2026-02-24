<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add composite indexes for team photo queries.
 *
 * These queries run constantly on the dashboard, approval queue, and map:
 *   photos WHERE team_id IN (...) AND created_at >= ...
 *   photos WHERE team_id = X AND is_public = false AND verified >= 1
 *   photos WHERE team_id = X AND user_id = Y (per-member counts)
 *
 * Also adds unique constraint on team_user pivot to prevent duplicate joins.
 */
return new class extends Migration
{
    public function up(): void
    {
        // These indexes may already exist from 2026_02_22_202000_add_photo_privacy.
        // Only add indexes that don't already exist.
        $existingIndexes = collect(
            \DB::select("SHOW INDEX FROM photos")
        )->pluck('Key_name')->unique()->toArray();

        Schema::table('photos', function (Blueprint $table) use ($existingIndexes) {
            if (! in_array('photos_team_public_created_idx', $existingIndexes)) {
                $table->index(['team_id', 'is_public', 'created_at'], 'photos_team_public_created_idx');
            }

            if (! in_array('photos_team_public_verified_created_idx', $existingIndexes)) {
                $table->index(
                    ['team_id', 'is_public', 'verified', 'created_at'],
                    'photos_team_public_verified_created_idx'
                );
            }
        });

        // Unique constraint on pivot — prevents duplicate joins
        Schema::table('team_user', function (Blueprint $table) {
            $tuIndexes = collect(
                \DB::select("SHOW INDEX FROM team_user")
            )->pluck('Key_name')->unique()->toArray();

            if (! in_array('team_user_team_id_user_id_unique', $tuIndexes)) {
                $table->unique(['team_id', 'user_id'], 'team_user_team_id_user_id_unique');
            }

            if (! in_array('team_user_user_id_team_id_idx', $tuIndexes)) {
                $table->index(['user_id', 'team_id'], 'team_user_user_id_team_id_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $existingIndexes = collect(
                \DB::select("SHOW INDEX FROM photos")
            )->pluck('Key_name')->unique()->toArray();

            if (in_array('photos_team_public_created_idx', $existingIndexes)) {
                $table->dropIndex('photos_team_public_created_idx');
            }
            if (in_array('photos_team_public_verified_created_idx', $existingIndexes)) {
                $table->dropIndex('photos_team_public_verified_created_idx');
            }
        });

        Schema::table('team_user', function (Blueprint $table) {
            $tuIndexes = collect(
                \DB::select("SHOW INDEX FROM team_user")
            )->pluck('Key_name')->unique()->toArray();

            if (in_array('team_user_team_id_user_id_unique', $tuIndexes)) {
                $table->dropUnique('team_user_team_id_user_id_unique');
            }
            if (in_array('team_user_user_id_team_id_idx', $tuIndexes)) {
                $table->dropIndex('team_user_user_id_team_id_idx');
            }
        });
    }
};

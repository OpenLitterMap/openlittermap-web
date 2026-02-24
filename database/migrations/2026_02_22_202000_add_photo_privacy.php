<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            // School team photos are private until teacher approves
            $table->boolean('is_public')->default(true)->after('verified');

            // Team verification workflow
            $table->timestamp('team_approved_at')->nullable()->after('is_public');
            $table->unsignedBigInteger('team_approved_by')->nullable()->after('team_approved_at');

            // ── Indexes for production queries ──────────────────────

            // Points API: Photo::public()->where('verified', '>=', 2)
            $table->index(['is_public', 'verified'], 'photos_public_verified_idx');

            // Team photo listing: where team_id = X and is_public = ...
            $table->index(['team_id', 'is_public'], 'photos_team_public_idx');

            // Team dashboard: where team_id in (...) and created_at >= ...
            $table->index(['team_id', 'created_at'], 'photos_team_created_idx');

            // Approval queue: where team_id = X and is_public = false and verified >= 1
            $table->index(['team_id', 'is_public', 'verified', 'created_at'], 'photos_team_approval_idx');

            // Per-user counts (avoid N+1): where team_id = X group by user_id
            $table->index(['team_id', 'user_id', 'created_at'], 'photos_team_user_idx');
        });

        // ── Pivot table constraints ─────────────────────────────

        // Prevent duplicate memberships (critical for counter consistency)
        Schema::table('team_user', function (Blueprint $table) {
            // Use raw SQL to check if the unique index already exists
            // before adding it — some codebases may already have this
        });

        // Add unique constraint if it doesn't exist
        $indexExists = collect(\DB::select("SHOW INDEX FROM team_user WHERE Key_name = 'team_user_team_id_user_id_unique'"))->isNotEmpty();

        if (! $indexExists) {
            Schema::table('team_user', function (Blueprint $table) {
                $table->unique(['team_id', 'user_id'], 'team_user_team_id_user_id_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex('photos_public_verified_idx');
            $table->dropIndex('photos_team_public_idx');
            $table->dropIndex('photos_team_created_idx');
            $table->dropIndex('photos_team_approval_idx');
            $table->dropIndex('photos_team_user_idx');
            $table->dropColumn(['is_public', 'team_approved_at', 'team_approved_by']);
        });

        $indexExists = collect(\DB::select("SHOW INDEX FROM team_user WHERE Key_name = 'team_user_team_id_user_id_unique'"))->isNotEmpty();

        if ($indexExists) {
            Schema::table('team_user', function (Blueprint $table) {
                $table->dropUnique('team_user_team_id_user_id_unique');
            });
        }
    }
};

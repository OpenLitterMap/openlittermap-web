<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Photos\DeletePhotoAction;
use App\Actions\Tags\AddTagsToPhotoAction;
use App\Enums\CategoryKey;
use App\Enums\VerificationStatus;
use App\Events\SchoolDataApproved;
use App\Events\TagsVerifiedByAdmin;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Teams\Team;
use App\Services\Metrics\MetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TeamPhotosController extends Controller
{
    /**
     * List photos for a team (private view — members only).
     *
     * GET /api/teams/photos?team_id=X&status=pending|approved|all&page=1
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'team_id' => 'required|exists:teams,id',
            'status' => 'nullable|in:pending,approved,all',
        ]);

        $user = auth()->user();
        $team = Team::findOrFail($request->team_id);

        if (! $user->teams()->where('team_id', $team->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'not-a-member'], 403);
        }

        $query = Photo::with([
                'photoTags.category:id,key',
                'photoTags.object:id,key',
                'photoTags.extraTags.extraTag',
                'user:id,name,username',
                'participant:id,slot_number,display_name',
            ])
            ->where('team_id', $team->id)
            ->orderByDesc('created_at');

        $status = $request->input('status', 'all');

        if ($status === 'pending') {
            $query->where('is_public', false)->whereNull('team_approved_at');
        } elseif ($status === 'approved') {
            $query->whereNotNull('team_approved_at');
        }

        $photos = $query->paginate(20);

        // Safeguarding — mask student names at API level
        $applySafeguarding = $team->safeguarding
            && ! $team->isLeader($user->id)
            && ! $user->can('view student identities');

        if ($applySafeguarding) {
            $this->applySafeguarding($photos->getCollection(), $team);
        }

        // Transform to include new_tags format for frontend tag hydration
        $photos->getCollection()->transform(function (Photo $photo) {
            $photo->setAttribute('new_tags', $this->getNewTags($photo));
            return $photo;
        });

        return response()->json([
            'success' => true,
            'photos' => $photos,
            'stats' => $this->teamPhotoStats($team->id),
        ]);
    }

    /**
     * Get a single photo with its tags (for editing).
     *
     * GET /api/teams/photos/{photo}
     */
    public function show(Photo $photo): JsonResponse
    {
        $user = auth()->user();

        if (! $photo->team_id) {
            return response()->json(['success' => false, 'message' => 'not-a-team-photo'], 404);
        }

        if (! $user->teams()->where('team_id', $photo->team_id)->exists()) {
            return response()->json(['success' => false, 'message' => 'not-a-member'], 403);
        }

        $photo->load([
            'photoTags.category:id,key',
            'photoTags.object:id,key',
            'photoTags.extraTags.extraTag',
            'user:id,name,username',
        ]);

        $photo->setAttribute('new_tags', $this->getNewTags($photo));

        return response()->json([
            'success' => true,
            'photo' => $photo,
        ]);
    }

    /**
     * Update tags on a team photo (teacher edit before approval).
     *
     * PATCH /api/teams/photos/{photo}/tags
     *
     * Accepts CLO-based payload (same format as PhotoTagsController::store).
     * Deletes existing tags, resets summary/xp/verified, then delegates
     * to AddTagsToPhotoAction to recreate tags with proper summary + XP.
     *
     * Only the team leader (teacher) or users with 'manage school team' permission.
     */
    public function updateTags(Request $request, Photo $photo): JsonResponse
    {
        $user = auth()->user();
        $team = Team::findOrFail($photo->team_id);

        if (! $team->isLeader($user->id) && ! $user->can('manage school team')) {
            return response()->json(['success' => false, 'message' => 'unauthorized'], 403);
        }

        $request->validate([
            'tags' => 'required|array|min:1',
            'tags.*.category_litter_object_id' => 'required|exists:category_litter_object,id',
            'tags.*.litter_object_type_id' => 'nullable|exists:litter_object_types,id',
            'tags.*.quantity' => 'required|integer|min:1',
            'tags.*.picked_up' => 'nullable|boolean',
            'tags.*.materials' => 'nullable|array',
            'tags.*.materials.*.id' => 'required|exists:materials,id',
            'tags.*.materials.*.quantity' => 'required|integer|min:1',
            'tags.*.brands' => 'nullable|array',
            'tags.*.brands.*.id' => 'required|exists:brands,id',
            'tags.*.brands.*.quantity' => 'required|integer|min:1',
            'tags.*.custom_tags' => 'nullable|array',
            'tags.*.custom_tags.*.tag' => 'required|string|max:100',
            'tags.*.custom_tags.*.quantity' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($request, $photo, $user) {
            // Delete existing tags (extra_tags cascade via FK)
            $photo->photoTags()->each(function ($tag) {
                $tag->extraTags()->delete();
                $tag->delete();
            });

            // Reset summary and XP so AddTagsToPhotoAction regenerates them.
            // Use VERIFIED (not UNVERIFIED) so school photos remain in the
            // facilitator queue's pending filter (verified >= VERIFIED).
            $photo->update([
                'summary' => null,
                'xp' => 0,
                'verified' => VerificationStatus::VERIFIED->value,
            ]);

            // Add new tags via the standard action (generates summary, XP)
            app(AddTagsToPhotoAction::class)->run(
                $user->id,
                $photo->id,
                $request->tags
            );
        });

        // Reload with full relationships for response
        $photo->refresh();
        $photo->load([
            'photoTags.category:id,key',
            'photoTags.object:id,key',
            'photoTags.extraTags.extraTag',
            'user:id,name,username',
        ]);

        $photo->setAttribute('new_tags', $this->getNewTags($photo));

        return response()->json([
            'success' => true,
            'photo' => $photo,
        ]);
    }

    /**
     * Approve photos — makes them public, fires metrics, notifies team.
     *
     * POST /api/teams/photos/approve
     *
     * Body: { photo_ids: [1, 2, 3] } or { team_id: X, approve_all: true }
     *
     * IDEMPOTENT: approving already-approved photos is a no-op.
     *   The WHERE clause includes is_public = 0, so re-approving does nothing.
     *
     * ATOMIC: all DB updates happen in a single UPDATE statement inside a
     *   transaction. Concurrent requests by two teachers won't double-process
     *   because the WHERE is_public = 0 condition eliminates already-flipped rows.
     *
     * SUMMARY REQUIREMENT: Each photo MUST have a non-null summary JSON
     *   before approval. The summary is generated by AddTagsToPhotoAction
     *   when the student tags the photo (regardless of trust level).
     *   If summary is null, MetricsService will extract zero metrics.
     *   We log a warning but still approve — the photo appears on the map,
     *   and the summary can be backfilled later.
     */
    public function approve(Request $request): JsonResponse
    {
        $user = auth()->user();

        $request->validate([
            'photo_ids' => 'required_without:approve_all|array',
            'photo_ids.*' => 'exists:photos,id',
            'team_id' => 'required|exists:teams,id',
            'approve_all' => 'nullable|boolean',
        ]);

        $team = Team::findOrFail($request->team_id);

        if (! $team->isLeader($user->id) && ! $user->can('manage school team')) {
            return response()->json(['success' => false, 'message' => 'unauthorized'], 403);
        }

        // Build query for unapproved photos only (idempotent)
        $query = Photo::where('team_id', $team->id)
            ->where('is_public', false);

        if ($request->approve_all) {
            $query->where('verified', '>=', VerificationStatus::VERIFIED->value);
        } else {
            $query->whereIn('id', $request->photo_ids);
        }

        $approvedIds = $query->pluck('id')->toArray();

        if (empty($approvedIds)) {
            return response()->json([
                'success' => true,
                'approved_count' => 0,
                'message' => 'No photos to approve.',
            ]);
        }

        // Single atomic update — WHERE is_public = 0 prevents double-processing
        $affectedRows = DB::transaction(function () use ($approvedIds, $user) {
            return Photo::whereIn('id', $approvedIds)
                ->where('is_public', false)
                ->update([
                    'is_public' => true,
                    'verified' => VerificationStatus::ADMIN_APPROVED->value,
                    'team_approved_at' => now(),
                    'team_approved_by' => $user->id,
                ]);
        });

        // Dispatch events only for actually-updated photos
        if ($affectedRows > 0) {
            $approvedPhotos = Photo::whereIn('id', $approvedIds)
                ->where('is_public', true)
                ->get();

            foreach ($approvedPhotos as $photo) {
                // Warn if summary is missing — metrics will extract zero
                if (empty($photo->summary)) {
                    Log::warning("Approving photo {$photo->id} with null summary — metrics will be incomplete");
                }

                // Teacher approval IS the verification event for school photos.
                // This triggers MetricsService through the existing
                // ProcessPhotoMetrics listener pipeline.
                event(new TagsVerifiedByAdmin(
                    photo_id: $photo->id,
                    user_id: $photo->user_id,
                    country_id: $photo->country_id,
                    state_id: $photo->state_id,
                    city_id: $photo->city_id,
                    team_id: $photo->team_id,
                ));
            }

            event(new SchoolDataApproved(
                team: $team,
                approvedBy: $user,
                photoCount: $affectedRows,
            ));
        }

        return response()->json([
            'success' => true,
            'approved_count' => $affectedRows,
            'message' => "{$affectedRows} photos approved and published.",
        ]);
    }

    /**
     * Per-member stats for the team (leader/facilitator only).
     *
     * GET /api/teams/photos/member-stats?team_id=X
     *
     * Returns per-student: total photos, pending, approved, litter count, last active.
     * Applies safeguarding pseudonyms when enabled.
     */
    public function memberStats(Request $request): JsonResponse
    {
        $request->validate([
            'team_id' => 'required|exists:teams,id',
        ]);

        $user = auth()->user();
        $team = Team::findOrFail($request->team_id);

        if (! $team->isLeader($user->id) && ! $user->can('manage school team')) {
            return response()->json(['success' => false, 'message' => 'unauthorized'], 403);
        }

        // Get all members (excluding leader)
        $members = $team->users()
            ->where('users.id', '!=', $team->leader)
            ->select('users.id', 'users.name', 'users.username')
            ->get();

        // Build safeguarding pseudonym map
        $pseudonyms = [];
        if ($team->safeguarding) {
            $memberOrder = DB::table('team_user')
                ->where('team_id', $team->id)
                ->where('user_id', '!=', $team->leader)
                ->orderBy('id')
                ->pluck('user_id')
                ->flip()
                ->map(fn ($index) => 'Student ' . ($index + 1))
                ->toArray();
            $pseudonyms = $memberOrder;
        }

        // Per-member photo stats in a single query
        $photoStats = DB::table('photos')
            ->where('team_id', $team->id)
            ->whereNull('deleted_at')
            ->whereIn('user_id', $members->pluck('id'))
            ->groupBy('user_id')
            ->selectRaw('
                user_id,
                COUNT(*) as total_photos,
                SUM(CASE WHEN is_public = 0 AND team_approved_at IS NULL THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN team_approved_at IS NOT NULL THEN 1 ELSE 0 END) as approved,
                COALESCE(SUM(total_tags), 0) as litter_count,
                MAX(created_at) as last_active
            ')
            ->get()
            ->keyBy('user_id');

        $memberData = $members->map(function ($member) use ($photoStats, $pseudonyms, $team) {
            $stats = $photoStats->get($member->id);

            return [
                'user_id' => $member->id,
                'name' => ! empty($pseudonyms)
                    ? ($pseudonyms[$member->id] ?? 'Student')
                    : $member->name,
                'username' => ! empty($pseudonyms) ? null : $member->username,
                'is_participant' => false,
                'total_photos' => $stats ? (int) $stats->total_photos : 0,
                'pending' => $stats ? (int) $stats->pending : 0,
                'approved' => $stats ? (int) $stats->approved : 0,
                'litter_count' => $stats ? (int) $stats->litter_count : 0,
                'last_active' => $stats?->last_active,
            ];
        });

        // Include participant stats if participant sessions are enabled
        if ($team->hasParticipantSessions()) {
            $participantStats = DB::table('photos')
                ->where('team_id', $team->id)
                ->whereNull('deleted_at')
                ->whereNotNull('participant_id')
                ->groupBy('participant_id')
                ->selectRaw('
                    participant_id,
                    COUNT(*) as total_photos,
                    SUM(CASE WHEN is_public = 0 AND team_approved_at IS NULL THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN team_approved_at IS NOT NULL THEN 1 ELSE 0 END) as approved,
                    COALESCE(SUM(total_tags), 0) as litter_count,
                    MAX(created_at) as last_active
                ')
                ->get()
                ->keyBy('participant_id');

            $participants = $team->participants()->orderBy('slot_number')->get();

            $participantData = $participants->map(function ($p) use ($participantStats) {
                $stats = $participantStats->get($p->id);

                return [
                    'participant_id' => $p->id,
                    'name' => $p->display_name,
                    'username' => null,
                    'is_participant' => true,
                    'total_photos' => $stats ? (int) $stats->total_photos : 0,
                    'pending' => $stats ? (int) $stats->pending : 0,
                    'approved' => $stats ? (int) $stats->approved : 0,
                    'litter_count' => $stats ? (int) $stats->litter_count : 0,
                    'last_active' => $p->last_active_at ?? $stats?->last_active,
                ];
            });

            $memberData = $memberData->concat($participantData)->values();
        }

        return response()->json([
            'success' => true,
            'members' => $memberData,
        ]);
    }

    /**
     * Team map data — private points for team members.
     *
     * GET /api/teams/photos/map?team_id=X
     */
    public function mapPoints(Request $request): JsonResponse
    {
        $request->validate([
            'team_id' => 'required|exists:teams,id',
        ]);

        $user = auth()->user();
        $team = Team::findOrFail($request->team_id);

        if (! $user->teams()->where('team_id', $team->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'not-a-member'], 403);
        }

        $points = Photo::where('team_id', $team->id)
            ->whereNotNull('lat')
            ->whereNotNull('lon')
            ->with(['user:id,name,username,show_username_maps,show_name_maps,global_flag'])
            ->select([
                'id', 'user_id', 'lat', 'lon', 'verified', 'is_public',
                'total_tags', 'remaining', 'filename', 'datetime', 'summary',
                'created_at',
            ])
            ->orderByDesc('created_at')
            ->limit(5000)
            ->get()
            ->map(function ($photo) use ($team) {
                $applySafeguarding = $team->safeguarding;

                return [
                    'id' => $photo->id,
                    'lat' => $photo->lat,
                    'lng' => $photo->lon,
                    'tags' => $photo->total_tags,
                    'verified' => $photo->verified->value,
                    'is_public' => $photo->is_public,
                    'date' => $photo->created_at->toDateString(),
                    // Popup fields — same shape as global map PointsController::show
                    'filename' => $photo->filename,
                    'datetime' => $photo->datetime,
                    'picked_up' => $photo->picked_up,
                    'summary' => $photo->summary,
                    'team' => $team->name,
                    'name' => $applySafeguarding ? null : (
                        $photo->user && $photo->user->show_name_maps ? $photo->user->name : null
                    ),
                    'username' => $applySafeguarding ? null : (
                        $photo->user && $photo->user->show_username_maps ? $photo->user->username : null
                    ),
                    'flag' => $applySafeguarding ? null : $photo->user?->global_flag,
                ];
            });

        return response()->json([
            'success' => true,
            'points' => $points,
        ]);
    }

    /**
     * Delete a team photo (teacher only).
     *
     * DELETE /api/teams/photos/{photo}?team_id=X
     *
     * Reverses metrics, deletes S3 files, soft-deletes the photo.
     */
    public function destroy(Request $request, Photo $photo): JsonResponse
    {
        $user = auth()->user();

        $request->validate([
            'team_id' => 'required|exists:teams,id',
        ]);

        $team = Team::findOrFail($request->team_id);

        if ((int) $photo->team_id !== (int) $team->id) {
            return response()->json(['success' => false, 'message' => 'photo-not-in-team'], 404);
        }

        if (! $team->isLeader($user->id) && ! $user->can('manage school team')) {
            return response()->json(['success' => false, 'message' => 'unauthorized'], 403);
        }

        // Reverse metrics before delete (if photo was processed)
        // MetricsService::deletePhoto() reverses both upload XP and tag XP
        // from MySQL metrics, Redis, and users.xp
        if ($photo->processed_at !== null) {
            app(MetricsService::class)->deletePhoto($photo);
        }

        // Delete S3 files
        app(DeletePhotoAction::class)->run($photo);

        // Hard delete — cascading FKs clean up photo_tags and extras
        $photo->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Photo deleted.',
            'stats' => $this->teamPhotoStats($team->id),
        ]);
    }

    /**
     * Revoke approval on team photos — makes them private again.
     *
     * POST /api/teams/photos/revoke
     *
     * Body: { team_id: X, photo_ids: [1, 2, 3] } or { team_id: X, revoke_all: true }
     *
     * IDEMPOTENT: WHERE clause filters out already-private photos.
     * Reverses metrics for any processed photos before un-publishing.
     */
    public function revoke(Request $request): JsonResponse
    {
        $user = auth()->user();

        $request->validate([
            'photo_ids' => 'required_without:revoke_all|array',
            'photo_ids.*' => 'exists:photos,id',
            'team_id' => 'required|exists:teams,id',
            'revoke_all' => 'nullable|boolean',
        ]);

        $team = Team::findOrFail($request->team_id);

        if (! $team->isLeader($user->id) && ! $user->can('manage school team')) {
            return response()->json(['success' => false, 'message' => 'unauthorized'], 403);
        }

        // Build query for approved photos only (idempotent)
        $query = Photo::where('team_id', $team->id)
            ->where('is_public', true)
            ->whereNotNull('team_approved_at');

        if (! $request->revoke_all) {
            $query->whereIn('id', $request->photo_ids);
        }

        $photosToRevoke = $query->get();

        if ($photosToRevoke->isEmpty()) {
            return response()->json([
                'success' => true,
                'revoked_count' => 0,
                'message' => 'No photos to revoke.',
            ]);
        }

        $metricsService = app(MetricsService::class);

        $revokedCount = DB::transaction(function () use ($photosToRevoke, $metricsService) {
            // Reverse metrics for each processed photo (BEFORE state change)
            foreach ($photosToRevoke as $photo) {
                if ($photo->processed_at !== null) {
                    $metricsService->deletePhoto($photo);
                }
            }

            // Atomic update with idempotency guard — only revoke still-public photos
            return Photo::whereIn('id', $photosToRevoke->pluck('id'))
                ->where('is_public', true)
                ->update([
                    'is_public' => false,
                    'verified' => VerificationStatus::VERIFIED->value,
                    'team_approved_at' => null,
                    'team_approved_by' => null,
                ]);
        });

        return response()->json([
            'success' => true,
            'revoked_count' => $revokedCount,
            'message' => "{$revokedCount} photos revoked.",
        ]);
    }

    // ─── Helpers ──────────────────────────────────────

    /**
     * Transform PhotoTag relationships into the new_tags format.
     *
     * Same pattern as AdminQueueController::getNewTags().
     */
    private function getNewTags(Photo $photo): array
    {
        if (! $photo->photoTags || $photo->photoTags->count() === 0) {
            return [];
        }

        $newTags = [];

        foreach ($photo->photoTags as $photoTag) {
            $tag = [
                'id' => $photoTag->id,
                'category_litter_object_id' => $photoTag->category_litter_object_id,
                'litter_object_type_id' => $photoTag->litter_object_type_id,
                'quantity' => $photoTag->quantity,
                'picked_up' => $photoTag->picked_up,
            ];

            if ($photoTag->category) {
                $tag['category'] = [
                    'id' => $photoTag->category->id,
                    'key' => $photoTag->category->key,
                ];
            }

            if ($photoTag->object) {
                $tag['object'] = [
                    'id' => $photoTag->object->id,
                    'key' => $photoTag->object->key,
                ];
            }

            $extraTags = [];
            foreach ($photoTag->extraTags as $extra) {
                $extraTag = [
                    'type' => $extra->tag_type,
                    'quantity' => $extra->quantity,
                ];

                if ($extra->extraTag) {
                    $extraTag['tag'] = [
                        'id' => $extra->extraTag->id,
                        'key' => $extra->extraTag->key,
                    ];
                }

                $extraTags[] = $extraTag;
            }

            if (! empty($extraTags)) {
                $tag['extra_tags'] = $extraTags;
            }

            $newTags[] = $tag;
        }

        return $newTags;
    }

    /**
     * Photo stats for a team — single query, no N+1.
     */
    protected function teamPhotoStats(int $teamId): array
    {
        return Cache::remember("team:{$teamId}:photo_stats", 120, function () use ($teamId) {
            $stats = DB::table('photos')
                ->where('team_id', $teamId)
                ->whereNull('deleted_at')
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN is_public = 0 AND team_approved_at IS NULL THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN team_approved_at IS NOT NULL THEN 1 ELSE 0 END) as approved
                ')
                ->first();

            return [
                'total' => (int) $stats->total,
                'pending' => (int) $stats->pending,
                'approved' => (int) $stats->approved,
            ];
        });
    }

    /**
     * Mask student identities for safeguarded teams.
     *
     * Uses deterministic pseudonyms based on team_user.id ordering —
     * "Student 3" is always the same person across requests and pages.
     * Stable because we order by pivot row ID (creation order), not by
     * photo data or pagination position.
     */
    protected function applySafeguarding($photos, Team $team): void
    {
        // Build stable mapping: user_id → "Student N"
        // Ordered by pivot row creation (team_user.id), so numbering is
        // deterministic regardless of which photos are on which page.
        $memberOrder = DB::table('team_user')
            ->where('team_id', $team->id)
            ->where('user_id', '!=', $team->leader)
            ->orderBy('id')
            ->pluck('user_id')
            ->flip()
            ->map(fn ($index) => 'Student ' . ($index + 1))
            ->toArray();

        $photos->transform(function ($photo) use ($memberOrder, $team) {
            if ($photo->user && $photo->user_id !== $team->leader) {
                $photo->user->name = $memberOrder[$photo->user_id] ?? 'Student';
                $photo->user->username = null;
            }
            return $photo;
        });
    }
}

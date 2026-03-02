<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VerificationStatus;
use App\Events\TagsVerifiedByAdmin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUsernameRequest;
use App\Mail\SchoolManagerInvite;
use App\Models\Photo;
use App\Models\Users\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminUsersController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    /**
     * List users with search, sort, filter, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'sort_by' => 'nullable|in:created_at,photos_count,xp',
            'sort_dir' => 'nullable|in:asc,desc',
            'trust_filter' => 'nullable|in:all,trusted,untrusted',
            'flagged' => 'nullable|in:true,false,1,0',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $perPage = min((int) ($request->per_page ?? 25), 100);

        $query = User::query()
            ->withCount('photos')
            ->with('roles:id,name');

        // Search by name, username, or email
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('username', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Trust filter
        $trustFilter = $request->input('trust_filter', 'all');
        if ($trustFilter === 'trusted') {
            $query->where('verification_required', false);
        } elseif ($trustFilter === 'untrusted') {
            $query->where('verification_required', true);
        }

        // Flagged usernames filter
        if ($request->has('flagged') && $request->input('flagged')) {
            $query->where('username_flagged', true);
        }

        // Sorting
        if ($sortBy === 'photos_count') {
            $query->orderBy('photos_count', $sortDir);
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $paginated = $query->paginate($perPage);

        $paginated->through(function (User $user) {
            $pendingPhotos = $user->photos()
                ->where('is_public', true)
                ->where('verified', '<', VerificationStatus::ADMIN_APPROVED->value)
                ->count();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'created_at' => $user->created_at?->toDateString(),
                'photos_count' => $user->photos_count,
                'xp' => $user->xp,
                'verification_required' => $user->verification_required,
                'pending_photos' => $pendingPhotos,
                'roles' => $user->roles->pluck('name')->toArray(),
                'is_trusted' => ! $user->verification_required,
                'username_flagged' => $user->username_flagged,
            ];
        });

        return response()->json([
            'success' => true,
            'users' => $paginated,
        ]);
    }

    /**
     * Toggle trust status for a user.
     *
     * Superadmin only. Sets verification_required = !trusted.
     * Does NOT retroactively approve existing photos.
     */
    public function trust(Request $request, User $user): JsonResponse
    {
        if (! auth()->user()->hasRole('superadmin')) {
            return response()->json(['message' => 'Superadmin role required.'], 403);
        }

        $request->validate([
            'trusted' => 'required|boolean',
        ]);

        $trusted = $request->boolean('trusted');
        $user->update(['verification_required' => ! $trusted]);

        // Note: logAdminAction() requires a Photo (photo_id NOT NULL in admin_verification_logs).
        // Trust actions target users, not photos. Audit log for user actions is a future schema change.

        return response()->json([
            'user_id' => $user->id,
            'trusted' => $trusted,
            'verification_required' => $user->verification_required,
        ]);
    }

    /**
     * Approve all pending public photos for a user.
     *
     * Superadmin only. Max 500 per call.
     * Fires TagsVerifiedByAdmin per approved photo.
     */
    public function approveAll(Request $request, User $user): JsonResponse
    {
        if (! auth()->user()->hasRole('superadmin')) {
            return response()->json(['message' => 'Superadmin role required.'], 403);
        }

        $photos = Photo::where('user_id', $user->id)
            ->where('is_public', true)
            ->where('verified', '<', VerificationStatus::ADMIN_APPROVED->value)
            ->whereNotNull('summary')
            ->limit(500)
            ->get();

        $approvedCount = 0;

        foreach ($photos as $photo) {
            $affected = Photo::where('id', $photo->id)
                ->where('is_public', true)
                ->where('verified', '<', VerificationStatus::ADMIN_APPROVED->value)
                ->update(['verified' => VerificationStatus::ADMIN_APPROVED->value]);

            if ($affected > 0) {
                $photo->refresh();

                event(new TagsVerifiedByAdmin(
                    $photo->id,
                    $photo->user_id,
                    $photo->country_id,
                    $photo->state_id,
                    $photo->city_id,
                    $photo->team_id,
                ));

                rewardXpToAdmin();
                $approvedCount++;
            }
        }

        return response()->json([
            'approved_count' => $approvedCount,
        ]);
    }

    /**
     * Toggle school_manager role for a user.
     *
     * Superadmin only. Assigns role + sets remaining_teams = 1 if granting.
     */
    public function toggleSchoolManager(Request $request, User $user): JsonResponse
    {
        if (! auth()->user()->hasRole('superadmin')) {
            return response()->json(['message' => 'Superadmin role required.'], 403);
        }

        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $enabled = $request->boolean('enabled');

        if ($enabled) {
            $user->assignRole('school_manager');

            if ($user->remaining_teams < 1) {
                $user->update(['remaining_teams' => 1]);
            }

            Mail::to($user)->queue(new SchoolManagerInvite($user));
        } else {
            $user->removeRole('school_manager');
        }

        return response()->json([
            'user_id' => $user->id,
            'school_manager' => $user->hasRole('school_manager'),
            'remaining_teams' => $user->remaining_teams,
        ]);
    }

    /**
     * Update a user's username (moderation).
     *
     * Superadmin only. Clears username_flagged after review.
     */
    public function updateUsername(UpdateUsernameRequest $request, User $user): JsonResponse
    {
        $oldUsername = $user->username;

        $user->update([
            'username' => $request->validated('username'),
            'username_flagged' => false,
        ]);

        // Note: logAdminAction() requires a Photo — user-target audit is a future schema change.

        return response()->json([
            'user_id' => $user->id,
            'username' => $user->username,
            'previous_username' => $oldUsername,
        ]);
    }
}

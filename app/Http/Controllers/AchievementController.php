<?php

namespace App\Http\Controllers;

use App\Models\Achievements\Achievement;
use App\Services\Achievements\AchievementQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    public function __construct(
        private AchievementQueryService $queryService
    ) {}

    /**
     * Get user's achievement summary
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $summary = $this->queryService->getUserSummary($user);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Get all achievements with user's progress
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|string|in:uploads,objects,categories,materials,brands,object,category,material,brand,customTag',
            'unlocked_only' => 'nullable|boolean',
            'locked_only' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $query = Achievement::query();

        // Filter by type if specified
        if (isset($validated['type'])) {
            $query->ofType($validated['type']);
        }

        // Filter by unlock status
        if ($validated['unlocked_only'] ?? false) {
            $query->whereHas('users', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } elseif ($validated['locked_only'] ?? false) {
            $query->notUnlockedBy($user);
        }

        $achievements = $query->paginate($validated['per_page'] ?? 20);

        // Add user-specific data to each achievement
        $achievements->getCollection()->transform(function ($achievement) use ($user) {
            $isUnlocked = $achievement->isUnlockedBy($user);

            return [
                'id' => $achievement->id,
                'type' => $achievement->type,
                'name' => $achievement->display_name,
                'description' => $achievement->description,
                'icon' => $achievement->icon,
                'threshold' => $achievement->threshold,
                'xp' => $achievement->xp,
                'is_unlocked' => $isUnlocked,
                'unlocked_at' => $isUnlocked
                    ? $achievement->users()->where('user_id', $user->id)->first()?->pivot->unlocked_at
                    : null,
                'progress' => !$isUnlocked ? $achievement->getProgressFor($user) : $achievement->threshold,
                'progress_percentage' => !$isUnlocked
                    ? $achievement->getProgressPercentageFor($user)
                    : 100,
                'remaining' => !$isUnlocked ? $achievement->getRemainingFor($user) : 0,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $achievements,
        ]);
    }

    /**
     * Get achievements close to being unlocked
     */
    public function nearCompletion(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = $request->input('limit', 10);

        $achievements = $this->queryService->getNextAchievableFor($user, $limit);

        $data = $achievements->map(function ($achievement) {
            return [
                'id' => $achievement->id,
                'type' => $achievement->type,
                'name' => $achievement->display_name,
                'description' => $achievement->description,
                'icon' => $achievement->icon,
                'threshold' => $achievement->threshold,
                'xp' => $achievement->xp,
                'progress' => $achievement->current_progress,
                'progress_percentage' => $achievement->progress_percentage,
                'remaining' => $achievement->remaining,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get recently unlocked achievements
     */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = $request->input('limit', 10);

        $achievements = $this->queryService->getRecentlyUnlocked($user, $limit);

        $data = $achievements->map(function ($achievement) {
            return [
                'id' => $achievement->id,
                'type' => $achievement->type,
                'name' => $achievement->display_name,
                'description' => $achievement->description,
                'icon' => $achievement->icon,
                'threshold' => $achievement->threshold,
                'xp' => $achievement->xp,
                'unlocked_at' => $achievement->unlocked_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get statistics for a specific achievement type
     */
    public function typeStats(Request $request, string $type): JsonResponse
    {
        $validTypes = ['uploads', 'objects', 'categories', 'materials', 'brands'];

        if (!in_array($type, $validTypes)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid achievement type',
            ], 400);
        }

        $user = $request->user();
        $stats = $this->queryService->getTypeStatistics($user, $type);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get a single achievement with user progress
     */
    public function show(Request $request, Achievement $achievement): JsonResponse
    {
        $user = $request->user();
        $isUnlocked = $achievement->isUnlockedBy($user);

        $data = [
            'id' => $achievement->id,
            'type' => $achievement->type,
            'name' => $achievement->display_name,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'threshold' => $achievement->threshold,
            'xp' => $achievement->xp,
            'metadata' => $achievement->metadata,
            'is_unlocked' => $isUnlocked,
            'unlocked_at' => $isUnlocked
                ? $achievement->users()->where('user_id', $user->id)->first()?->pivot->unlocked_at
                : null,
            'progress' => !$isUnlocked ? $achievement->getProgressFor($user) : $achievement->threshold,
            'progress_percentage' => !$isUnlocked
                ? $achievement->getProgressPercentageFor($user)
                : 100,
            'remaining' => !$isUnlocked ? $achievement->getRemainingFor($user) : 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}

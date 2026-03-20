<?php

namespace App\Http\Controllers\Teams;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TeamsDataController extends Controller
{
    /**
     * Get the combined effort for 1 or all of the user's teams for the time-period.
     *
     * GET /api/teams/data?team_id=0&period=all
     *
     * Stats are queried from the photos table directly — NOT from stale
     * teams.total_images / teams.total_litter counters (listeners deleted in v5).
     *
     * Uses photos.total_tags (v5) — NOT total_litter (deprecated, scheduled for drop).
     */
    public function index(): JsonResponse
    {
        $teamIds = $this->getTeamIds();

        if (empty($teamIds)) {
            return response()->json([
                'photos_count' => 0,
                'litter_count' => 0,
                'members_count' => 0,
                'verification' => $this->emptyVerification(),
            ]);
        }

        $period = $this->resolvePeriod(request()->input('period', 'all'));

        // Photos uploaded in period
        $photosQuery = Photo::query()
            ->whereIn('team_id', $teamIds)
            ->where('created_at', '>=', $period);

        $photosCount = $photosQuery->count();
        $membersCount = (clone $photosQuery)->distinct()->count('user_id');

        // Litter tagged in period (admin-approved or better)
        $litterCount = Photo::query()
            ->whereIn('team_id', $teamIds)
            ->where('created_at', '>=', $period)
            ->where('verified', '>=', VerificationStatus::ADMIN_APPROVED->value)
            ->sum('total_tags');

        // Verification breakdown
        $verification = $this->verificationBreakdown($teamIds, $period);

        return response()->json([
            'photos_count' => $photosCount,
            'litter_count' => (int) $litterCount,
            'members_count' => $membersCount,
            'verification' => $verification,
        ]);
    }

    /**
     * Verification status breakdown for dashboard display.
     */
    protected function verificationBreakdown(array $teamIds, string $period): array
    {
        $counts = Photo::query()
            ->whereIn('team_id', $teamIds)
            ->where('created_at', '>=', $period)
            ->select('verified', DB::raw('COUNT(*) as count'))
            ->groupBy('verified')
            ->pluck('count', 'verified');

        return [
            'unverified' => (int) ($counts[VerificationStatus::UNVERIFIED->value] ?? 0),
            'verified' => (int) ($counts[VerificationStatus::VERIFIED->value] ?? 0),
            'admin_approved' => (int) ($counts[VerificationStatus::ADMIN_APPROVED->value] ?? 0),
            'bbox_applied' => (int) ($counts[VerificationStatus::BBOX_APPLIED->value] ?? 0),
            'bbox_verified' => (int) ($counts[VerificationStatus::BBOX_VERIFIED->value] ?? 0),
            'ai_ready' => (int) ($counts[VerificationStatus::AI_READY->value] ?? 0),
        ];
    }

    protected function emptyVerification(): array
    {
        return [
            'unverified' => 0,
            'verified' => 0,
            'admin_approved' => 0,
            'bbox_applied' => 0,
            'bbox_verified' => 0,
            'ai_ready' => 0,
        ];
    }

    /**
     * Resolve the period start date from a named period.
     */
    protected function resolvePeriod(string $period): string
    {
        return match ($period) {
            'today' => now()->startOfDay()->toDateTimeString(),
            'week' => now()->startOfWeek()->toDateTimeString(),
            'month' => now()->startOfMonth()->toDateTimeString(),
            'year' => now()->startOfYear()->toDateTimeString(),
            default => '2020-01-01 00:00:00',
        };
    }

    /**
     * Get the team IDs to query.
     */
    protected function getTeamIds(): array
    {
        $teamIds = Auth::user()->teams->pluck('id')->toArray();

        $requestedTeamId = request()->input('team_id');

        if ($requestedTeamId && in_array((int) $requestedTeamId, $teamIds, true)) {
            return [(int) $requestedTeamId];
        }

        return $teamIds;
    }
}

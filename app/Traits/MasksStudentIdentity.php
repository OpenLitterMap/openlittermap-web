<?php

namespace App\Traits;

use App\Models\Teams\Team;
use App\Models\Users\User;
use Illuminate\Support\Facades\DB;

/**
 * API-level identity masking for school teams with safeguarding enabled.
 *
 * When a team has safeguarding=true, student names and usernames are replaced
 * with deterministic pseudonyms ("Student 1", "Student 2" etc.) based on
 * team_user.id (join order), so numbering is stable across requests.
 *
 * Unmasked view is granted to:
 * - The team leader (teacher)
 * - Anyone with the 'view student identities' permission (admin)
 */
trait MasksStudentIdentity
{
    protected function applySafeguarding($members, Team $team, User $viewer)
    {
        if (! $team->safeguarding) {
            return $members;
        }

        // Leader and admins with permission see real names
        // Use permissions relationship directly to avoid auth guard mismatch
        // (API routes use 'api' guard but Spatie permissions are on 'web' guard)
        if ($team->isLeader($viewer->id) || $viewer->permissions()->where('name', 'view student identities')->exists()) {
            return $members;
        }

        // Build stable mapping: user_id → "Student N"
        // Ordered by pivot row creation (team_user.id), so numbering is
        // deterministic regardless of tag counts or pagination.
        $pseudonyms = DB::table('team_user')
            ->where('team_id', $team->id)
            ->where('user_id', '!=', $team->leader)
            ->orderBy('id')
            ->pluck('user_id')
            ->flip()
            ->map(fn ($index) => 'Student ' . ($index + 1))
            ->toArray();

        $data = is_array($members) ? $members : $members->toArray();

        if (isset($data['data'])) {
            foreach ($data['data'] as &$member) {
                $userId = $member['id'] ?? $member['user_id'] ?? null;
                $member['name'] = $pseudonyms[$userId] ?? 'Student';
                $member['username'] = null;
            }
        } else {
            foreach ($data as &$member) {
                $userId = $member['id'] ?? $member['user_id'] ?? null;
                $member['name'] = $pseudonyms[$userId] ?? 'Student';
                $member['username'] = null;
            }
        }

        return $data;
    }
}

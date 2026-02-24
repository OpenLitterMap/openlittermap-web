<?php

namespace App\Traits;

use App\Models\Teams\Team;
use App\Models\Users\User;

/**
 * API-level identity masking for school teams with safeguarding enabled.
 *
 * When a team has safeguarding=true, student names and usernames are replaced
 * with "Student 1", "Student 2" etc.
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

        $data = is_array($members) ? $members : $members->toArray();
        $counter = 1;

        if (isset($data['data'])) {
            foreach ($data['data'] as &$member) {
                $member['name'] = "Student {$counter}";
                $member['username'] = null;
                $counter++;
            }
        } else {
            foreach ($data as &$member) {
                $member['name'] = "Student {$counter}";
                $member['username'] = null;
                $counter++;
            }
        }

        return $data;
    }
}

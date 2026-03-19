<?php

namespace App\Http\Controllers\API;

use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\Location\Country;
use App\Models\Cleanups\Cleanup;
use App\Models\AdminVerificationLog;
use App\Http\Controllers\Controller;
use App\Payment;
use App\Services\Redis\RedisKeys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class DeleteAccountController extends Controller
{
    /**
     * Delete a user account (GDPR-compliant).
     *
     * Photos are preserved as anonymous public contributions (user_id set to NULL).
     * The migration makes photos.user_id nullable with ON DELETE SET NULL,
     * so the database handles photo anonymization automatically on user deletion.
     */
    public function __invoke(Request $request)
    {
        $user = Auth::user();

        if (! Hash::check($request->password, $user->password)) {
            return [
                'success' => false,
                'msg' => 'password does not match',
            ];
        }

        $userId = $user->id;

        // Collect Redis scopes BEFORE nullifying user_id on photos
        // (need to know which locations the user contributed to)
        $redisScopes = $this->collectRedisScopes($userId);

        try {
            AdminVerificationLog::where('admin_id', $userId)->delete();

            DB::table('cleanup_user')->where('user_id', $userId)->delete();
            Cleanup::where('user_id', $userId)->delete();

            // Nullify location ownership
            Country::where('created_by', $userId)->update(['created_by' => null]);
            State::where('created_by', $userId)->update(['created_by' => null]);
            City::where('created_by', $userId)->update(['created_by' => null]);

            DB::table('model_has_roles')
                ->where('model_type', 'App\Models\Users\User')
                ->where('model_id', $userId)
                ->delete();

            DB::table('oauth_access_tokens')
                ->where('user_id', $userId)
                ->delete();

            // Reassign payments rather than deleting (preserves financial records)
            Payment::where('user_id', $userId)->update(['user_id' => 1]);

            DB::table('subscriptions')->where('user_id', $userId)->delete();
            DB::table('team_user')->where('user_id', $userId)->delete();

            // Detach photos from teams before deleting teams (team_id FK is RESTRICT)
            $teamIds = Team::where('leader', $userId)
                ->orWhere('created_by', $userId)
                ->pluck('id');

            if ($teamIds->isNotEmpty()) {
                Photo::whereIn('team_id', $teamIds)->update(['team_id' => null]);
                Team::whereIn('id', $teamIds)->delete();
            }

            // Nullify merchant photo ownership
            DB::table('merchant_photos')
                ->where('uploaded_by', $userId)
                ->update(['uploaded_by' => 1]);

            // Delete per-user metrics rows (prevents ghost leaderboard entries)
            DB::table('metrics')
                ->where('user_id', $userId)
                ->delete();

            // Clean up Redis: remove user from all leaderboards and delete user hashes
            $this->cleanupRedis($userId, $redisScopes);
        } catch (\Exception $e) {
            Log::info(['DeleteAccountController relationship cleanup', $e->getMessage()]);

            return [
                'success' => false,
                'msg' => 'problem deleting user relationships',
            ];
        }

        try {
            // photos.user_id and photos.verified_by are SET NULL on delete
            // so the database automatically anonymizes photos
            $user->delete();
        } catch (\Exception $e) {
            Log::info(['DeleteAccountController user delete', $e->getMessage()]);

            return [
                'success' => false,
                'msg' => 'problem deleting user',
            ];
        }

        return ['success' => true];
    }

    /**
     * Collect Redis scopes from the user's photos BEFORE they are anonymized.
     */
    private function collectRedisScopes(int $userId): array
    {
        $scopes = [RedisKeys::global()];

        $locationIds = Photo::where('user_id', $userId)
            ->select('country_id', 'state_id', 'city_id')
            ->distinct()
            ->get();

        foreach ($locationIds as $row) {
            if ($row->country_id) {
                $scopes[] = RedisKeys::country($row->country_id);
            }
            if ($row->state_id) {
                $scopes[] = RedisKeys::state($row->state_id);
            }
            if ($row->city_id) {
                $scopes[] = RedisKeys::city($row->city_id);
            }
        }

        return array_unique($scopes);
    }

    /**
     * Remove user from all Redis leaderboards and delete user-specific keys.
     */
    private function cleanupRedis(int $userId, array $scopes): void
    {
        try {
            Redis::pipeline(function ($pipe) use ($scopes, $userId) {
                $userIdStr = (string) $userId;

                foreach ($scopes as $scope) {
                    $pipe->zRem(RedisKeys::contributorRanking($scope), $userIdStr);
                    $pipe->zRem(RedisKeys::xpRanking($scope), $userIdStr);
                }

                // Delete user-specific hashes and bitmap
                $userScope = RedisKeys::user($userId);
                $pipe->del(RedisKeys::stats($userScope));
                $pipe->del("{$userScope}:tags");
                $pipe->del(RedisKeys::userBitmap($userId));
            });
        } catch (\Exception $e) {
            Log::warning('Redis cleanup failed during account deletion', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

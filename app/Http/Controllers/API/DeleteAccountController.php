<?php

namespace App\Http\Controllers\API;

use App\Payment;
use App\Models\Photo;
use App\Models\Littercoin;
use App\Models\Teams\Team;
use App\Models\AI\Annotation;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\Location\Country;
use App\Models\Cleanups\Cleanup;
use App\Models\AdminVerificationLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DeleteAccountController extends Controller
{
    /**
     * Try to delete a User
     */
    public function __invoke (Request $request)
    {
        $user = Auth::guard('api')->user();

        // Check the users password matches
        if (!Hash::check($request->password, $user->password)) {
            return [
                'success' => false,
                'msg' => 'password does not match'
            ];
        }

        $userId = $user->id;

        // user.photos
        Photo::where('user_id', $userId)
            ->chunk(1000, function ($photos) use ($userId)
            {
                foreach ($photos as $photo)
                {
                    foreach ($photo->categories() as $category)
                    {
                        try
                        {
                            if ($photo->$category)
                            {
                                $photo->$category->delete();
                            }

                            if (sizeof($photo->customTags) > 0)
                            {
                                $photo->customTags->each->delete();
                            }

                            $adminVerificationLogs = AdminVerificationLog::where([
                                'admin_id' => $userId,
                                'photo_id' => $photo->id
                            ])->count();

                            if ($adminVerificationLogs > 0)
                            {
                                AdminVerificationLog::where([
                                    'admin_id' => $userId,
                                    'photo_id' => $photo->id
                                ])->delete();
                            }

                            $annotations = Annotation::where([
                                'photo_id' => $photo->id
                            ])->count();

                            if ($annotations > 0)
                            {
                                Annotation::where([
                                    'photo_id' => $photo->id
                                ])->delete();
                            }

                            $littercoin = Littercoin::where([
                                'user_id' => $userId,
                                'photo_id' => $photo->id
                            ])->count();

                            if ($littercoin > 0)
                            {
                                Littercoin::where([
                                    'user_id' => $userId,
                                    'photo_id' => $photo->id
                                ])->delete();
                            }

                            $photo->delete();
                        }
                        catch (\Exception $e)
                        {
                            \Log::info(['DeleteAccountController', $e->getMessage()]);

                            return [
                                'success' => false,
                                'msg' => 'problem deleting photo'
                            ];
                        }
                    }
                }
            });

        try
        {
            $adminVerificationLogs = AdminVerificationLog::where('admin_id', $userId)->count();

            if ($adminVerificationLogs > 0)
            {
                AdminVerificationLog::where('admin_id', $userId)->delete();
            }

            $cleanupUser = DB::table('cleanup_user')->where('user_id', $userId)->count();

            if ($cleanupUser > 0)
            {
                DB::table('cleanup_user')->where('user_id', $userId)->delete();
            }

            $cleanups = Cleanup::where('user_id', $userId)->count();

            if ($cleanups > 0)
            {
                Cleanup::where('user_id', $userId)->delete();
            }

            $countriesCreatedBy = Country::select('id', 'created_by')
                ->where('created_by', $userId)
                ->get();

            if (sizeof($countriesCreatedBy) > 0)
            {
                foreach ($countriesCreatedBy as $country)
                {
                    $country->created_by = null;
                    $country->save();
                }
            }

            $countriesLastUploaded = Country::select('id', 'user_id_last_uploaded')
                ->where('user_id_last_uploaded', $userId)
                ->get();

            if (sizeof($countriesLastUploaded) > 0)
            {
                foreach ($countriesLastUploaded as $country)
                {
                    $country->user_id_last_uploaded = null;
                    $country->save();
                }
            }

            $statesCreatedBy = State::select('id', 'created_by')
                ->where('created_by', $userId)
                ->get();

            if (sizeof($statesCreatedBy) > 0)
            {
                foreach ($statesCreatedBy as $state)
                {
                    $state->created_by = null;
                    $state->save();
                }
            }

            $statesLastUploaded = State::select('id', 'user_id_last_uploaded')
                ->where('user_id_last_uploaded', $userId)
                ->get();

            if (sizeof($statesLastUploaded) > 0)
            {
                foreach ($statesLastUploaded as $state)
                {
                    $state->user_id_last_uploaded = null;
                    $state->save();
                }
            }

            $citiesCreatedBy = City::select('id', 'created_by')
                ->where('created_by', $userId)
                ->get();

            if (sizeof($citiesCreatedBy) > 0)
            {
                foreach ($citiesCreatedBy as $city)
                {
                    $city->created_by = null;
                    $city->save();
                }
            }

            $citiesLastUploaded = City::select('id', 'user_id_last_uploaded')
                ->where('user_id_last_uploaded', $userId)
                ->get();

            if (sizeof($citiesLastUploaded) > 0)
            {
                foreach ($citiesLastUploaded as $city)
                {
                    $city->user_id_last_uploaded = null;
                    $city->save();
                }
            }

            $littercoin = Littercoin::where('user_id', $userId)->get();

            if (sizeof($littercoin) > 0)
            {
                foreach ($littercoin as $ltc)
                {
                    $ltc->delete();
                }
            }

            $modelHasRoles = DB::table('model_has_roles')
                ->where('model_type', 'App\Models\User\User')
                ->where('model_id', $userId)
                ->count();

            if ($modelHasRoles > 0)
            {
                DB::table('model_has_roles')
                    ->where('model_type', 'App\Models\User\User')
                    ->where('model_id', $userId)
                    ->delete();
            }

            $oauthTokens = DB::table('oauth_access_tokens')
                ->where('user_id', $userId)
                ->count();

            if ($oauthTokens > 0)
            {
                DB::table('oauth_access_tokens')
                    ->where('user_id', $userId)
                    ->delete();
            }

            // payments
            $payments = Payment::where('user_id', $userId)->get();

            if (sizeof($payments) > 0)
            {
                foreach ($payments as $payment)
                {
                    // should we do something on stripe with stripe_id?
                    $payment->user_id = 1;
                    $payment->save();
                }
            }

            // photos
            // subscriptions
            $subscriptions = DB::table('subscriptions')
                ->where('user_id', $userId)
                ->count();

            if ($subscriptions > 0 )
            {
                DB::table('subscriptions')
                    ->where('user_id', $userId)
                    ->delete();
            }

            // team_user
            $teamUsers = DB::table('team_user')
                ->where('user_id', $userId)
                ->count();

            if ($teamUsers > 0)
            {
                DB::table('team_user')
                    ->where('user_id', $userId)
                    ->delete();
            }

            $teams = Team::where('leader', $userId)
                ->where('leader', $userId)
                ->orWhere('created_by', $userId)
                ->get();

            foreach ($teams as $team)
            {
                // remove other team members?
                $team->delete();
            }
        }
        catch (\Exception $e)
        {
            \Log::info(['DeleteAccountController', $e->getMessage()]);

            return [
                'success' => false,
                'msg' => 'problem deleting user relationships'
            ];
        }

        try
        {
            $user->delete();
        }
        catch (\Exception $e)
        {
            \Log::info(['DeleteAccountController', $e->getMessage()]);

            return [
                'succcess' => false,
                'msg' => 'problem deleting user'
            ];
        }

        return [
            'success' => true
        ];

        // These are our relationships
        // user.photo_id exists
        // - smoking
        // - food
        // - coffee
        // - softdrinks
        // - alcohol
        // - coastal
        // - other
        // - sanitary
        // - brands
        // - dumping
        // - industrial
        // - material
        // custom_tags
        // admin_verification_tag.admin_id & photo_id
        // annotations
        // littercoins (user_id, photo_id)

        // user_id exists
        // admin_verification_tag
        // cleanup_user
        // cleanups
        // countries.created_at, user_id_last_uploaded
        // states.created_by, user_id_last_uploaded
        // cities.created_by, user_id_last_uploaded
        // littercoin

        // model_has_roles
        // oauth_access_tokens
        // payments
        // photos
        // subscriptions
        // team_user
        // teams.leader
        // teams.created_by
        // users
    }
}

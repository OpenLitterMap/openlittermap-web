<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AdminVerificationLog;
use App\Models\AI\Annotation;
use App\Models\Cleanups\Cleanup;
use App\Models\Littercoin;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Teams\Team;
use App\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeleteAccountController extends Controller
{
    /**
     * Try to delete a User
     */
    public function __invoke ()
    {
        $user = Auth::guard('api')->user();

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
                                'user_id' => $userId,
                                'photo_id' => $photo->id
                            ])->count();

                            if ($adminVerificationLogs > 0)
                            {
                                AdminVerificationLog::where([
                                    'user_id' => $userId,
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
            $adminVerificationLogs = AdminVerificationLog::where('user_id', $userId)->count();

            if ($adminVerificationLogs > 0)
            {
                AdminVerificationLog::where('user_id', $userId)->delete();
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

            $countries = Country::select('id', 'created_by', 'user_id_last_uploaded')
                ->where('created_by', $userId)
                ->orWhere('user_id_last_uploaded', $userId)
                ->get();

            if (sizeof($countries) > 0)
            {
                foreach ($countries as $country)
                {
                    $country->created_by = null;
                    $country->user_id_last_uploaded = null;
                    $country->save();
                }
            }

            $states = State::select('id', 'created_by', 'user_id_last_uploaded')
                ->where('created_by', $userId)
                ->orWhere('user_id_last_uploaded', $userId)
                ->get();

            if (sizeof($states) > 0)
            {
                foreach ($states as $state)
                {
                    $state->created_by = null;
                    $state->user_id_last_uploaded = null;
                    $state->save();
                }
            }

            $cities = City::select('id', 'created_by', 'user_id_last_uploaded')
                ->where('created_by', $userId)
                ->orWhere('user_id_last_uploaded', $userId)
                ->get();

            if (sizeof($cities) > 0)
            {
                foreach ($cities as $city)
                {
                    $city->created_by = null;
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
                ->get();

            if (sizeof($modelHasRoles) > 0)
            {
                foreach ($modelHasRoles as $modelHasRole)
                {
                    $modelHasRole->delete();
                }
            }

            $oauthTokens = DB::table('oauth_access_tokens')
                ->where('user_id', $userId)
                ->get();

            if (sizeof($oauthTokens) > 0)
            {
                foreach ($oauthTokens as $oauthToken)
                {
                    $oauthToken->delete();
                }
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
                ->get();

            if (sizeof($subscriptions) > 0 )
            {
                foreach ($subscriptions as $subscription)
                {
                    $subscription->delete();
                }
            }

            // team_user
            $teamUsers = DB::table('team_user')
                ->where('user_id', $userId)
                ->get();

            foreach ($teamUsers as $teamUser)
            {
                $teamUser->delete();
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

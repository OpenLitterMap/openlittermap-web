<?php

namespace App\Http\Controllers;

use Auth;
use App\Level;
use Illuminate\Http\Request;

class LitterController extends Controller
{
    /**
     * Get the page to tag litter to a photo 
     */
    public function tag()
    {
        $locale = \App::getLocale();
        $user = Auth::user();
        $subscription = '';

        if(!$user->stripe_Id) {
            $subscription = 'Free';
        } else {
            $subscription = $user->subscriptions->name;
        }

        // Check the users level, update xp bar 
        $levels = Level::all();
        foreach($levels as $level) {
            if ($user->xp > $level->xp) {
                $user->level = $level->id;
                $user->save();
            }
        }

        if ($user->level == 0) {
            $startingXP = 0;
            $xpNeeded = 10;
        } else {
            // How much XP is needed for the next level?
            $xpNeeded = $levels[$user->level]['xp'];
            // Previous XP for 0-1 effect 
            $startingXP = $levels[$user->level-1]['xp'];
        }

        // Get the photos as pagination x 1 
        $photos = $user->photos()->where([
            ['verified', 0],
            ['verification', 0]
        ])->paginate(1); // length aware paginator class 

        $littercoin = 0;

        // littercoin earned by adding locations to database
        if ($user->littercoin_owed) {
            $littercoin += $user->littercoin_owed;
        }

        // littercoin earned by producing open data        
        if ($user->littercoin_allowance) {
            $littercoin += $user->littercoin_allowance;
        }

        return view('pages.litter.tag', [
            'user' => $user, 
            'photos' => $photos,
            // 'tasks' => $tasks,
            'xpNeeded' => $xpNeeded,
            'startingXP' => $startingXP,
            // 'photosPerMonthString' => $photosPerMonthString,
            // 'awards' => json_encode($awardsArray),
            'subscription' => $subscription,
            'littercoinowed' => $littercoin,
            'locale' => $locale
        ]);
    }
}

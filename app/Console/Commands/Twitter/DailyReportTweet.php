<?php

namespace App\Console\Commands\Twitter;

use Carbon\Carbon;
use App\Models\Photo;
use Spatie\Emoji\Emoji;
use App\Helpers\Twitter;
use App\Models\User\User;
use App\Models\Littercoin;
use App\Models\Location\Country;
use Illuminate\Console\Command;

class DailyReportTweet extends Command
{
    protected $signature = 'twitter:daily-report';

    protected $description = 'Send a daily report about OLM to Twitter OLM_bot account';

    public function handle ()
    {
        $startOfYesterday = Carbon::yesterday()->startOfDay();
        $endOfYesterday = Carbon::yesterday()->endOfDay();

        // total users
        $users = User::whereDate('created_at', '>=', $startOfYesterday)
            ->whereDate('created_at', '<=', $endOfYesterday)
            ->count();

        // total uploads/photos
        $todaysPhotosCount = Photo::whereDate('created_at', '>=', $startOfYesterday)
            ->whereDate('created_at', '<=', $endOfYesterday)
            ->count();

        $countries = Country::whereDate('updated_at', '>=', $startOfYesterday)
            ->whereDate('updated_at', '<=', $endOfYesterday)
            ->count();

        $totalUsers = User::count();

        $tags = Photo::whereDate('created_at', '>=', $startOfYesterday)
            ->whereDate('created_at', '<=', $endOfYesterday)
            ->sum('total_litter');

        // new locations

        // total littercoin
        $littercoinCount = Littercoin::whereDate('created_at', '>=', $startOfYesterday)
            ->whereDate('created_at', '<=', $endOfYesterday)
            ->count();

        // top countries
        $photos = Photo::select('id', 'created_at', 'country_id', 'total_litter')
            ->whereDate('created_at', '>=', $startOfYesterday)
            ->whereDate('created_at', '<=', $endOfYesterday)
            ->get();

        $countryIds = [];

        foreach ($photos as $photo)
        {
            if (!array_key_exists($photo->country_id, $countryIds))
            {
                $countryIds[$photo->country_id] = 0;
            }

            $countryIds[$photo->country_id] += $photo->total_litter;
        }

        arsort($countryIds);

        $first_three_keys = array_slice(array_keys($countryIds), 0, 3);

        $firstFlag = false;
        $secondFlag = false;
        $thirdFlag = false;

        if (isset($first_three_keys[0])) {
            $firstCountryId = $first_three_keys[0];

            $firstCountry = Country::select('id', 'shortcode')
                ->where('id', $firstCountryId)
                ->first();

            $firstFlag = Emoji::countryFlag($firstCountry->shortcode);
        }

        if (isset($first_three_keys[1])) {
            $secondCountryId = $first_three_keys[1];

            $secondCountry = Country::select('id', 'shortcode')
                ->where('id', $secondCountryId)
                ->first();

            $secondFlag = Emoji::countryFlag($secondCountry->shortcode);
        }

        if (isset($first_three_keys[2])) {
            $thirdCountryId = $first_three_keys[2];

            $thirdCountry = Country::select('id', 'shortcode')
                ->where('id', $thirdCountryId)
                ->first();

            $thirdFlag = Emoji::countryFlag($thirdCountry->shortcode);
        }

        $message = "Today we signed up $users users and uploaded $todaysPhotosCount photos from $countries countries!";
        $message .= " We added $tags tags.";
        $message .= " We now have $totalUsers users!";
        $message .= " $littercoinCount littercoin were mined.";

        if ($firstFlag) {
            $message .= " 1st $firstFlag ";

            if ($secondFlag) {
                $message .= " 2nd $secondFlag ";

                if ($thirdFlag) {
                    $message .= " 3rd $thirdFlag";
                }
            }
        }

        $message .= " #openlittermap #OLMbot 🌍";

        Twitter::sendTweet($message);
    }
}

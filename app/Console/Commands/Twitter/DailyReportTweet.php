<?php

namespace App\Console\Commands\Twitter;

use App\Helpers\Twitter;
use App\Models\Littercoin;
use Carbon\Carbon;
use App\Models\Photo;
use App\Models\User\User;
use App\Models\Location\Country;
use Illuminate\Console\Command;
use Abraham\TwitterOAuth\TwitterOAuth;


class DailyReportTweet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twitter:daily-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a daily report about OLM to Twitter OLM_bot account';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $startOfYesterday = Carbon::yesterday()->startOfDay();
        $endOfYesterday = Carbon::yesterday()->endOfDay();

        // total users
        $users = User::whereDate('created_at', '>=', $startOfYesterday)
            ->whereDate('created_at', '<=', $endOfYesterday)
            ->count();

        // total uploads/photos
        $photos = Photo::whereDate('created_at', '>=', $startOfYesterday)
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

        $message = "Today we signed up $users users and uploaded $photos photos from $countries countries!";
        $message .= " We added $tags tags.";
        $message .= " We now have $totalUsers users!";
        $message .= " $littercoinCount littercoin were mined";
        $message .= " #openlittermap #OLMbot 🌍";

        Twitter::sendTweet($message);
    }
}

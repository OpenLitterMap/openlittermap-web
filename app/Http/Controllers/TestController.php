<?php

namespace App\Http\Controllers;

use App\Models\User\User;
use Illuminate\Http\Request;

use DB;
use Auth;
use Carbon\Carbon;
use App\Models\Photo;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\Location\Country;

use App\Models\Litter\Categories\Smoking;
use App\Models\Litter\Categories\Alcohol;
use App\Models\Litter\Categories\Coffee;
use App\Models\Litter\Categories\Dumping;
use App\Models\Litter\Categories\Industrial;
use App\Models\Litter\Categories\Food;
use App\Models\Litter\Categories\SoftDrinks;
use App\Models\Litter\Categories\Drugs;
use App\Models\Litter\Categories\Sanitary;
use App\Models\Litter\Categories\Other;
use App\Models\Litter\Categories\Coastal;
use App\Models\Litter\Categories\Pathway;
use App\Models\Litter\Categories\Art;
use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Categories\TrashDog;

use DateTime;
use JavaScript;
use GeoJson\Feature\FeatureCollection;
use App\Http\Requests;
use Illuminate\Support\Facades\Redis;

class TestController extends Controller
{

    /**
     * Apply admin middleware to these routes
     */
    // public function __construct() {
    //     return $this->middleware('admin');
    //     parent::__construct();
    // }

    public function doSomething ()
    {
        return 'asdasdasdads';
    }

    public function getTest ()
    {
        return view('pages.testsubmit');
    }

    public function postTest(Request $request) {
        return [$request, 'hello'];
    }

//     public function test() {
//         $users = User::all();
//         return $users;
//         // return view('emails.smallupdate', compact('user'));
//     }

// //
//     public function tasdest() {

//         $countries = Country::where([
//             ['total_images', '>', '0'],
//             ['id', '!=', '16'],
//             ['manual_verify', '1']
//         ])->orderBy('country', 'asc')->get();

//         $total_litter = 0;
//         $total_photos = 0;

//         $arrayOfCreated = [];
//         // organize photo counts for each country by month
//         foreach($countries as $country) {
//             // create empty array for each country
//             $photosPerMonth = [];
//             // group the photos by month
//             $photos = Photo::where([
//                 ['country_id', $country->id],
//                 ['verified', '>', 0]
//             ])->orderBy('datetime', 'asc')->get()->groupBy(function($val) {
//                 return Carbon::parse($val->datetime)->format('m-y');
//             });

//             $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
//             // $itr = 0;
//             foreach($photos as $index => $monthlyPhotos) {
//                 $month = $months[(int)$substr = substr($index, 0, 2)];
//                 $year = substr($index, 2, 5);
//                 $photosPerMonth[$month.$year] = $monthlyPhotos->count(); // Mar-17
//                 $total_photos += $monthlyPhotos->count();
//             }

//             // create a new value on each country
//             $country['ppm'] = json_encode($photosPerMonth);

//             // return $photosPerMonth;

//             // Find out who created the Country
//             // $created = User::find($country->photos->first()->user_id);

//             array_push($arrayOfCreated, [$country->country, $country->photos->first()->user_id]);
//       //       if($created->show_name == 1) {
//       //        $country["created_by_name"] = $created->name;
//       //       }

//       //       if($created->show_username == 1) {
//       //        $country["created_by_username"] = ' @'.$created->username;
//       //       }

//             // if(!isset($country->created_by_name)) {
//             //  if(!isset($country->created_by_username)) {
//             //      $country["created_by_name"] = 'Anonymous';
//             //  }
//             // }

//             // Get the leaderboard from Redis
//             $leaderboard = Redis::zrevrange($country->country.':Leaderboard', 0, 10);
//             $arrayOfLeaders = [];
//             $newIndex = 0;
//             foreach($leaderboard as $index => $leader) {

//                 $a = User::find($leader);
//                 $name = '';
//                 $username = '';

//                 if(($a->show_name == 1) | ($a->show_username == 1)) {
//                     if($a->show_name == 1) {
//                         $name = $a->name;
//                     }
//                     if($a->show_username == 1) {
//                         $username = $username.'@'.$a->username;
//                     }
//                     $arrayOfLeaders[$newIndex] = [
//                         'position' => $newIndex,
//                         'name' => $name,
//                         'username' => $username,
//                         'xp' => $a->xp,
//                         'level' => $a->level,
//                         'created_at' => $a->created_at->diffForHumans(),
//                         'total_images' => $a->total_images,
//                         'total_butts' => $a->total_cigaretteButts
//                     ];
//                     $newIndex++;
//                 }
//             }
//             // return $arrayOfLeaders;
//             $country['leaderboard'] = json_encode($arrayOfLeaders);
//             $country['avg_photo_per_user'] = ($country->total_contributors / $country->total_images);
//             $country['total_litter'] = $country->total_smoking + $country->total_food + $country->total_softdrinks + $country->total_alcohol + $country->total_coffee + $country->total_drugs + $country->total_needles + $country->total_sanitary + $country->total_other;

//             $total_litter += $country['total_litter'];
//         }

//         // GLOBAL LITTER MAPPERS
//         $globalLeaders = [];
//         $newIndex = 0;
//         $users = User::where('has_uploaded', 1)->orderBy('xp', 'decs')->get();

//         foreach($users as $user) {
//             $name = '';
//             $username = '';
//             if(($user->show_name == 1) | ($user->show_username == 1)) {
//                 if($user->show_name == 1) {
//                     $name = $user->name;
//                 }
//                 if($user->show_username == 1) {
//                     $username = '@'.$user->username;
//                 }
//                 $globalLeaders[$newIndex] = [
//                     'position' => $newIndex,
//                     'name' => $name,
//                     'username' => $username,
//                     'xp' => $user->xp,
//                     'level' => $user->level,
//                     'created_at' => $user->created_at->diffForHumans(),
//                     'total_images' => $user->total_images,
//                     'total_butts' => $user->total_cigaretteButts
//                 ];
//                 $newIndex++;
//             }

//             // if(sizeof($globalLeaders) == /10) {
//                 // $globalLeadersString = json_encode($globalLeaders);
//                 // return $arrayOfCreated;
//                 // return view('pages.locations.countries', ['countries' => $countries, 'total_litter' => $total_litter, 'total_photos' => $total_photos, 'globalLeaders' => $globalLeadersString]);
//             // }
//         }
//         return $arrayOfCreated;
//     }
}

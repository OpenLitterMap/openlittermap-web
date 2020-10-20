<?php

namespace App\Http\Controllers;

use Log;
use Auth;
use File;
use App\Models\User\User;
use App\Models\Photo;

use App\Models\Litter\Categories\Smoking as Smoking;
use App\Models\Litter\Categories\Alcohol as Alcohol;
use App\Models\Litter\Categories\Coffee as Coffee;
use App\Models\Litter\Categories\Food as Food;
use App\Models\Litter\Categories\SoftDrinks as SoftDrinks;
use App\Models\Litter\Categories\Drugs as Drugs;
use App\Models\Litter\Categories\Sanitary as Sanitary;
use App\Models\Litter\Categories\Other as Other;
use App\Models\Litter\Categories\Coastal as Coastal;
use App\Models\Litter\Categories\Pathway as Pathway;
use App\Models\Litter\Categories\Art as Art;
use App\Models\Litter\Categories\Brand as Brand;
use App\Models\Litter\Categories\TrashDog as TrashDog;
use App\Models\Litter\Categories\Dumping as Dumping;
use App\Models\Litter\Categories\Industrial as Industrial;

use App\Litterrata;
use App\LitterES;
use App\Events\PhotoVerifiedByAdmin;
use Carbon\Carbon;

use App\Http\Requests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class AdminController extends Controller
{
    /**
     * Apply IsAdmin middleware to all of these routes
     */
    public function __construct ()
    {
    	return $this->middleware('admin');

    	parent::__construct();
	}

//    /**
//     * Load vue-router admin tool
//     */
//    public function index ()
//    {
//        return view('layouts.admin');
//    }

    public function getUserCount ()
    {
        // $users = User::where([
        //     ['verified', 1],
        //     ['has_uploaded', 1]
        // ])->orderBy('xp', 'desc')->get();

        $users = User::where('verified', 1)
            ->orWhere('name', 'default')
            ->get()
            ->sortBy('created_at');

        $totalUsers = $users->count();

        $users = $users->groupBy(function($val) {
            return Carbon::parse($val->created_at)->format('m-y');
        });;

        $upm = [];
        $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        // $itr = 0;
        foreach($users as $index => $monthlyUser)
        {
            // return [$index, $monthlyUser->count()];
            $month = $months[(int)$substr = substr($index, 0, 2)];
            $year = substr($index, 2, 5);
            $upm[$month.$year] = $monthlyUser->count(); // Mar-17
            // $total_photos += $monthlyUser->count();
        }
        $upm = json_encode($upm);

        $usersUploaded = User::where('has_uploaded', 1)->get();

        $usersUploaded = $usersUploaded->groupBy(function($val) {
            return Carbon::parse($val->created_at)->format('m-y');
        });;

        $uupm = [];
        foreach($usersUploaded as $index => $userUploaded)
        {
            // return $userUploaded->count();
            // return [$index, $userUploaded->count(), $userUploaded];
            $month = $months[(int)$substr = substr($index, 0, 2)];
            $year = substr($index, 2, 5);
            $uupm[$month.$year] = $userUploaded->count(); // Mar-17
            // $total_photos += $monthlyUser->count();
        }
        $uupm = json_encode($uupm);

        return view('admin.usercount', compact('users', 'totalUsers', 'upm', 'uupm'));
    }


    /**
     * Get Photos @ 0.1 - 1 verification
     */
    // public function getPhotos() {
        // Get the first photo submitted for verification
        // $photo = Photo::where([
        //     ['verified', '<', 2], // not verified
        //     ['verification', '>', 0] // submitted for verification
        // ])->first();

        // // Count photos submitted for verification
        // $photosCount = Photo::where([
        //     ['verified', '<', 2], // not verified
        //     ['verification', '>', 0] // submitted for verification
        // ])->count();

        // else, process other images uploaded but not processed
        // if (! $photo) $photo = Photo::where('verification', 0)->first();

        // if (! $photo) return view('admin.nophotos');

        // $userWhoUploaded = User::find($photo['user_id']);

        // note - not using this anymore, todo - write smart contracts and automate Littercoin distribution
        // Check if the Users Ltrx allowance is Greater than 0
        // if ($userWhoUploaded->littercoin_allowance > 0) {
        //     // check if user has a wallet id
        //     if ($userWhoUploaded->eth_wallet) {
        //         // if so, display the ltrx button
        //         $eth_wallet = $userWhoUploaded->eth_wallet;
        //     } else {
        //         $eth_wallet = '';
        //     }

        // } else {
        //     $eth_wallet = '';
        // }

    //     return view('admin.newphototool', compact(
    //         'photo',
    //         'photosCount',
    //         'photosNotProcessedCount',
    //         'userWhoUploaded',
    //         'eth_wallet',
    //         'photodata',
    //         'userId'
    //     ));
    // }

    /**
     * Verify an image, delete the image
     ** todo - fix this with correct AWS permissions
     */
    // public function verify(Request $request) {
    //     $photo = Photo::find($request->photoId);
    //     $filepath = $photo->filename;
    //     unlink(public_path($filepath));
    //     $photo->filename = '/assets/verified.jpg';
    //     $photo->verified = 2;
    //     $photo->verification = 1;
    //     $photo->save();
    //     $user = User::find($photo->user_id);
    //     $user->xp += 1;
    //     $user->save();
    //     event(new PhotoVerifiedByAdmin($photo->id));
    // }

    /**
     * Verify an image - keep the image
     */
    public function verifykeepimage (Request $request)
    {
      $photo = Photo::find($request->photoId);
      $photo->verified = 2;
      $photo->verification = 1;
      $photo->save();
      $user = User::find($photo->user_id);
      $user->xp += 1;
      $user->save();
      // todo - horizon
      event(new PhotoVerifiedByAdmin($photo->id));
    }

    /**
     * Incorrect image - reset verification to 0
     */
    public function incorrect (Request $request)
    {
        $this->reset($request->photoId);

        $user = Auth::user();
        $user->xp -= 1;
        $user->count_correctly_verified = 0;
        $user->save();
    }

    /**
     * Delete litter data associated with a photo
     */
    protected function reset ($id)
    {
        $photo = Photo::find($id);
        $photo->verification = 0;
        $photo->verified = 0;
        $photo->total_litter = 0;
        $photo->result_string = null;
        $photo->save();

        if ($photo["smoking_id"])
        {
            $smoking_id = $photo["smoking_id"];
            $photo["smoking_id"] = null;
            $photo->save();
            Smoking::find($smoking_id)->delete();
        }

        if ($photo["food_id"])
        {
            $food_id = $photo["food_id"];
            $photo["food_id"] = null;
            $photo->save();
            Food::find($food_id)->delete();
        }

        if ($photo["coffee_id"])
        {
            $coffee_id = $photo["coffee_id"];
            $photo["coffee_id"] = null;
            $photo->save();
            Coffee::find($coffee_id)->delete();
        }

        if ($photo["softdrinks_id"])
        {
            $softdrinks_id = $photo["softdrinks_id"];
            $photo["softdrinks_id"] = null;
            $photo->save();
            SoftDrinks::find($softdrinks_id)->delete();
        }

        if ($photo["alcohol_id"])
        {
            $alcohol_id = $photo["alcohol_id"];
            $photo["alcohol_id"] = null;
            $photo->save();
            Alcohol::find($alcohol_id)->delete();
        }

        if ($photo["other_id"])
        {
            $other_id = $photo["other_id"];
            $photo["other_id"] = null;
            $photo->save();
            Other::find($other_id)->delete();
        }

        if ($photo["sanitary_id"])
        {
            $sanitary_id = $photo["sanitary_id"];
            $photo["sanitary_id"] = null;
            $photo->save();
            Sanitary::find($sanitary_id)->delete();
        }

        if ($photo["coastal_id"])
        {
            $coastal_id = $photo["coastal_id"];
            $photo["coastal_id"] = null;
            $photo->save();
            Coastal::find($coastal_id)->delete();
        }

        if ($photo["art_id"])
        {
            $art_id = $photo["art_id"];
            $photo["art_id"] = null;
            $photo->save();
            Art::find($art_id)->delete();
        }

        if ($photo["trashdog_id"])
        {
            $trashdog_id = $photo["trashdog_id"];
            $photo["trashdog_id"] = null;
            $photo->save();
            TrashDog::find($trashdog_id)->delete();
        }

        if ($photo["dumping_id"])
        {
            $dumping_id = $photo["dumping_id"];
            $photo["dumping_id"] = null;
            $photo->save();
            Dumping::find($dumping_id)->delete();
        }

        if ($photo["industrial_id"])
        {
            $industrial_id = $photo["industrial_id"];
            $photo["industrial_id"] = null;
            $photo->save();
            Industrial::find($industrial_id)->delete();
        }
    }

    /**
     * Delete an image and its records
     */
    public function destroy (Request $request)
    {
        $photo = Photo::find($request->photoId);
        $s3 = \Storage::disk('s3');

        try {
            if (app()->environment('production'))
            {
                $path = substr($photo->filename, 42);
                $s3->delete($path);
            }
            $photo->delete();
        } catch (Exception $e) {
            \Log::info(["Admin delete failed", $e]);
        }

        return redirect()->back();
    }

    /**
      * Update the contents of an Image, Delete the image
     */
    public function updateDelete (Request $request)
    {
        $photo = Photo::find($request->photoId);
        $user = User::find($photo->user_id);

        $filepath = $photo->filename;
        // unlink(public_path($filepath));
        $photo->filename = '/assets/verified.jpg';

        $photo->verification = 1;
        $photo->verified = 2;
        $photo->total_litter = 0;

        $litterTotal = 0;

        $jsonDecoded = Litterrata::INSTANCE()->getDecodedJSON();

        // for each categories as category => values eg. Smoking, Butts: 3;
        foreach ($request['categories'] as $category => $values){
            $total = 0;
            foreach ($values as $item => $quantity){
                // return [$item, $quantity];
                // reference the dynamic id on the photos table eg. smoking_id
                $id          = $jsonDecoded->$category->id;
                // The current Class as a string
                $clazz       = $jsonDecoded->$category->class;
                // Reference the name of the column we want to edit
                // $col         = $jsonDecoded->$category->types->$item->att;

                $col         = $jsonDecoded->$category->types->$item->col;

                $dynamicClassName = 'App\\Categories\\'.$clazz;

                // Check if photo id already exists in the dynamic table
                // .... not actually sure if this 2-way binding this necessary
                if (!$dynamicClassName::where(['photo_id' => $photo->id])->first()){
                    // if not, create it
                    $dynamicClassName::create(['photo_id' => $photo->id]);
                }

                // Get the row (id) in the dynamic class we are currently working on
                $row = $dynamicClassName::where(['photo_id' => $photo->id])->first();
                // was previously named type

                // Does the photos table have a reference to the dynamic row id yet?
                if ($photo->$id == null) {
                    // if null, create the link
                    $photo->$id = $row->id;
                    $photo->save();
                }

                // Now that the tables are linked, update the dynamic row/col quantity and save
                // row = id, photo id, all attriubutes for that specific row
                // col == butts
                $row->$col = $quantity;
                $row->save();

                $litterTotal += $quantity;

            } // end foreach item

        } // end foreach categories as category

        $photo->total_litter = $litterTotal;
        $photo->save();

        // todo - horizon
        event(new PhotoVerifiedByAdmin($photo->id));
    }


    /**
     * Update the contents of an Image, Keep the image
     * Image was not correctly inputted! LitterCorrectlyCount = 0.
     */
    public function updateKeep (Request $request)
    {
        $photo = Photo::find($request->photoId);
        $user = User::find($photo->user_id);

        $user->count_correctly_verified = 0;
        $user->save();

        $photo->verification = 1;
        $photo->verified = 2;
        $photo->total_litter = 0;

        $litterTotal = 0;

        $jsonDecoded = Litterrata::INSTANCE()->getDecodedJSON();

        // for each categories as category => values eg. Smoking, Butts: 3;
        foreach ($request['categories'] as $category => $values)
        {
            // \Log::info(['category', $category]);
            // \Log::info(['values', $values]);

            // if there are no values, set the smoking_id, coffee_id to null
            // Todo - check if all values are 0 and set to null
            if (sizeof($values) == 0) {
                $id = $jsonDecoded->$category->id;
                $photo->$id = null;
            }

            $total = 0;
            foreach ($values as $item => $quantity)
            {
                // reference the dynamic id on the photos table eg. smoking_id
                $id          = $jsonDecoded->$category->id;
                // The current Class as a string
                $clazz       = $jsonDecoded->$category->class;
                // Reference the name of the column we want to edit
                $col         = $jsonDecoded->$category->types->$item->col;
                // return [$id, $clazz, $col, $att];
                $dynamicClassName = 'App\\Categories\\'.$clazz;

                // Create and select new row in the dynamic table
                // $row = $dynamicClassName::create();

                // Does the photos table have a reference to the dynamic row id yet?
                if (is_null($photo->$id)) {
                    // \Log::info('photo->$id is null...');
                    $row = $dynamicClassName::create();
                    $photo->$id = $row->id;
                    $photo->save();
                } else {
                    // \Log::info('photo->$id is NOT null...');
                    $row = $dynamicClassName::find($photo->$id);
                }

                // Now that the tables are linked, update the dynamic row/col quantity and save
                // row = id, photo id, all attriubutes for that specific row
                // col == butts
                $row->$col = $quantity;
                $row->save();
                $litterTotal += $quantity;
            } // end foreach item
        } // end foreach categories as category

        // photo->verified_by ;
        $photo->total_litter = $litterTotal;
        $photo->result_string = null;
        $photo->save();

        event(new PhotoVerifiedByAdmin($photo->id));
    }

    /**
     * Get the next image to verify
     */
    public function getImage ()
    {
        if ($photo = Photo::where('verification', 0.1)->first())
        {
            $photoData = $this->getPhotoData($photo);
        }

        else
        {
            $photo = Photo::where([
                'verification' => 0,
                // ['user_id', '!=', 1]
            ])->first();
            $photoData = null;
        }

        // Count photos that have been uploaded, but not tagged or submitted for verification
        $photosNotProcessed = Photo::where([
                ['verification', 0],
                // ['user_id', '!=', 1]
            ])->count();

        // Count photos submitted for verification
        $photosAwaitingVerification = Photo::where([
            ['verified', '<', 2], // not verified
            ['verification', '>', 0], // submitted for verification
            // ['user_id', '!=', 1]
        ])->count();

        return [
            'photo' => $photo,
            'photoData' => $photoData,
            'photosNotProcessed' => $photosNotProcessed,
            'photosAwaitingVerification' => $photosAwaitingVerification
        ];
    }

    /**
     * Get the litter data for the image
     */
    protected function getPhotoData ($photo)
    {
        $photodata = [];

        if ($photo->smoking_id)
        {
            $photodata['smoking'] = Smoking::find($photo->smoking_id);
        }

        if ($photo->coffee_id)
        {
            $photodata['coffee'] = Coffee::find($photo->coffee_id);
        }

        if ($photo->food_id)
        {
            $photodata['food'] = Food::find($photo->food_id);
        }

        if ($photo->alcohol_id)
        {
            $photodata['alcohol'] = Alcohol::find($photo->alcohol_id);
        }

        if ($photo->softdrinks_id)
        {
            $photodata['softdrinks'] = SoftDrinks::find($photo->softdrinks_id);
        }

        // if ($photo['drugs_id'])
        //{
        //     $drugs = Drugs::find($photo['drugs_id']);
        //     $photodata['Drugs'] = $drugs;
        // }

        if ($photo->sanitary_id)
        {
            $photodata['sanitary'] = Sanitary::find($photo->sanitary_id);
        }

        if ($photo->other_id)
        {
            $photodata['other'] = Other::find($photo->other_id);
        }

        if ($photo->coastal_id)
        {
            $photodata['coastal'] = Coastal::find($photo->coastal_id);
        }

        // if ($photo['pathways_id'])
        //{
        //     $pathway = Pathway::find($photo['pathways_id']);
        //     $photodata['Pathway'] = $pathway;
        // }

        if ($photo->art_id)
        {
            $photodata['art'] = Art::find($photo->art_id);
        }

        if ($photo->trashdog_id)
        {
            $photodata['trashdog'] = TrashDog::find($photo->trashdog_id);
        }

        if ($photo->brands_id)
        {
            $photodata['brands'] = Brand::find($photo->brands_id);
        }

        if ($photo->dumping_id)
        {
            $photodata['dumping'] = Dumping::find($photo->dumping_id);
        }

        if ($photo->industrial_id)
        {
            $photodata['industrial'] = Industrial::find($photo->industrial_id);
        }

        return $photodata;
    }

}

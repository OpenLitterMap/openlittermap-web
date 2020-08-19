<?php

namespace App\Http\Controllers;

use Auth;
use App\User;
use App\Photo;
use JavaScript;
use App\Categories\Smoking;
use App\Categories\Alcohol;
use App\Categories\Coffee;
use App\Categories\Food;
use App\Categories\SoftDrinks;
use App\Categories\Drugs;
use App\Categories\Sanitary;
use App\Categories\Other;
use App\Categories\Coastal;
use App\Categories\Pathway;
use App\Categories\Art;
use App\Categories\Brand;
use App\Categories\TrashDog;
use App\Litterrata;
use Illuminate\Http\Request;
use App\Events\PhotoVerifiedByUser;

class VerificationController extends Controller
{
	 /**
	  * Apply middleware to all of these routes
	  */ 
	public function __construct() {
	  	return $this->middleware('auth');
	  	parent::__construct();
	}

  //   public function getPending() {
  //       $user = Auth::user();
  //       $photos = $user->photos()->where('verification', 0.1)->paginate(1); // length aware paginator class 

  //       $photodata = [];

  //       $tasks = [
  //           ['Upload Profile Photo', false],
  //           ['Upload First Image', false],
  //           ['Process First Image', false],
  //           ['Verify someone elses image', false]
  //       ];

  //       // todo: Remove null values from the final request 
  //       foreach ($photos as $photo) {

  //       	if ($photo['smoking_id']) {
  //       		$smoking = Smoking::where('id', $photo['smoking_id'])->get();
  //       		$photodata['Smoking'] = $smoking;
  //       	}

  //       	if ($photo['coffee_id']) {
  //       		$coffee = Coffee::where('id', $photo['coffee_id'])->get();
  //       		$photodata['Coffee'] = $coffee;
  //       	}

  //       	if ($photo['food_id']) {
  //       		$food = Food::where('id', $photo['food_id'])->get();
  //       		$photodata['Food'] = $food;
  //       	}

  //       	if ($photo['alcohol_id']) {
  //       		$alcohol = Alcohol::where('id', $photo['alcohol_id'])->get();
  //       		$photodata['Alcohol'] = $alcohol;
  //       	}

  //       	if ($photo['softdrinks_id']) {
  //       		$softdrinks = SoftDrinks::where('id', $photo['softdrinks_id'])->get();
  //       		$photodata['SoftDrinks'] = $softdrinks;
  //       	}

  //       	if ($photo['drugs_id']) {
  //       		$drugs = Drugs::where('id', $photo['drugs_id'])->get();
  //       		$photodata['Drugs'] = $drugs;
  //       	}

  //       	if ($photo['sanitary_id']) {
  //       		$sanitary = Sanitary::where('id', $photo['sanitary_id'])->get();
  //       		$photodata['Sanitary'] = $sanitary;
  //       	}

  //       	if ($photo['other_id']) {
  //       		$other = Other::where('id', $photo['other_id'])->get();
  //       		$photodata['Other'] = $other;
  //       	}
  //       }

  //       \JavaScript::put([
		//   'photodata' => $photodata
		// ]);

  //       return view('user.pending', [
  //           'user' => $user, 
  //           'photos' => $photos,
  //           // 'photodata' => $photodata,
  //           'tasks' => $tasks
  //       ]);


  //   }

    /**
    * Get a random photo needed for verification + its contents 
    */
    public function getVerification() {

        $user = Auth::user();

        $subscription = '';

        if(!$user->stripe_Id) {
            $subscription = 'Free';
        } else {
            $subscription = $user->subscriptions[0]->name;
        }

        $photosVerifiedCount = Photo::where('verified', 1)->get()->count();
        $photosNotVerifiedCount = Photo::where('verified', 0)->get()->count();
        $hasUploadedCount = User::where('has_uploaded', 1)->get()->count();

        // avg photo 
        $allPhotoCount = (int)$photosVerifiedCount + (int)$photosNotVerifiedCount;
        $avgPhotoPerUser = $allPhotoCount / $hasUploadedCount;

        // contributor rankings 
        // move to Redis 

        // Get a random photo between submitted for verification + almost verified
        // join columns with the 'and' condition 
        $photosToVerify = Photo::where([
            ['verified', 0],
            ['user_id', '!=', $user->id],
            ['verification', '>=', '0.1'],
            ['verification', '<=', '0.99']
        ])->get();

        $photosToVerifyCount = $photosToVerify->count();

        if ($photosToVerifyCount == 0) {
           return view('pages.forms.nothingtoverify', compact('user', 'photosVerifiedCount', 'photosNotVerifiedCount', 'photosToVerifyCount', 'hasUploadedCount', 'avgPhotoPerUser', 'subscription'));
        }

        $photo = $photosToVerify->random();

        $photodata = [];

        if ($photo['smoking_id']) {
            $smoking = Smoking::where('id', $photo['smoking_id'])->get();
            $photodata['Smoking'] = $smoking;
        }

        if ($photo['coffee_id']) {
            $coffee = Coffee::where('id', $photo['coffee_id'])->get();
            $photodata['Coffee'] = $coffee;
        }

        if ($photo['food_id']) {
            $food = Food::where('id', $photo['food_id'])->get();
            $photodata['Food'] = $food;
        }

        if ($photo['alcohol_id']) {
            $alcohol = Alcohol::where('id', $photo['alcohol_id'])->get();
            $photodata['Alcohol'] = $alcohol;
        }

        if ($photo['softdrinks_id']) {
            $softdrinks = SoftDrinks::where('id', $photo['softdrinks_id'])->get();
            $photodata['SoftDrinks'] = $softdrinks;
        }

        if ($photo['drugs_id']) {
            $drugs = Drugs::where('id', $photo['drugs_id'])->get();
            $photodata['Drugs'] = $drugs;
        }

        if ($photo['sanitary_id']) {
            $sanitary = Sanitary::where('id', $photo['sanitary_id'])->get();
            $photodata['Sanitary'] = $sanitary;
        }

        if ($photo['other_id']) {
            $other = Other::where('id', $photo['other_id'])->get();
            $photodata['Other'] = $other;
        }

        if($photo['coastal_id']) {
          $coastal = Coastal::where('id', $photo['coastal_id'])->get();
          $photodata['Coastal'] = $coastal;
        }

        if($photo['pathway_id']) {
          $pathway = Pathway::where('id', $photo['pathway_id'])->get();
          $photodata['Pathway'] = $pathway;
        }

        if($photo['brands_id']) {
          $brands = Brand::where('id', $photo['brands_id'])->get();
          $photodata['Brand'] = $brands;
        }

        \JavaScript::put([
          'photodata' => $photodata
        ]);


        // todo: Include uploaded by 
        $uploader = User::find($photo->user_id);

        $uploaderString = '';

        if ($uploader->show_name == 1) {
          $uploaderString = $uploader->name;
        }

        if ($uploader->show_username == 1) {
          $uploaderString = $uploaderString . ' ' . '@' . $uploader->username;
        }

        if ($uploaderString == '') {
          $uploaderString = 'anon';
        }
        // todo
        // Total verified images 
        // $totalVerifiedImages = Photo::where(['verified', 1])->get()->count();

        // Total veririfed litter 

        return view('pages.forms.verify', compact('user', 'photosVerifiedCount', 'photosNotVerifiedCount', 'hasUploadedCount', 'avgPhotoPerUser', 'photo', 'photosToVerifyCount', 'subscription', 'uploaderString'));

    }

    /**
    * Post True or False for verification 
    */
    public function verify(Request $request) {

        // true of false? 
        $status = $request->status;

        // id of the image
        $id = $request->photoId;
        $photo = Photo::find($id);

        // Decrement number of times the user can verify
        $user = Auth::user();
        $user->verify_remaining -= 1;
        $user->save();


        // decrement 
        if ($status == 0) {

            if ($photo->verification == 0.1) {
                $photo->incorrect_verification += 1;
                $photo->save();

                if ($photo->incorrect_verification >= 5) {
                    // mark photo for deletion
                    $photo->verified = 999;
                    $photo->verification = 1.0;
                    $photo->save();
                }

            } else {
              $photo->verification -= 0.1;
              $photo->save();
            }
        }

        // increment 
        if ($status == 1) {
            $photo->verification += 0.1;
            $photo->save();
            $user->xp += 1;
            $user->save();

            if ($photo->verification > 0.99) {
                $photo->verified = 1;
                $photo->save();

                // user who confirms the image gets 1 xp
                $user->xp += 1;
                $user->save();
                
                // The photo has been verified! 
                // Update cities totals 
                // Update countries totals 
                // Update leaderboards 
                // Generate litter coin 
                // Share between the uploader (50%), the verifiers (25%) and us (25%);
                // Reward the user more as the level up
                event(new PhotoVerifiedByUser($photo->id));

                // return ['status' => 'Congratulations! Photo Verified! +10 xp for you'];
            }

        };



    }




}

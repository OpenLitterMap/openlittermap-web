<?php

namespace App\Http\Controllers;

use App\Events\ResetTagsCountAdmin;
use App\Litterrata;
use Illuminate\Support\Facades\File;
use Log;
use Auth;

use App\Models\Photo;
use App\Models\User\User;

use App\Traits\AddTagsTrait;

use Carbon\Carbon;

// use App\Http\Requests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

use App\Events\TagsVerifiedByAdmin;

class AdminController extends Controller
{
    use AddTagsTrait;

    /**
     * Apply IsAdmin middleware to all of these routes
     */
    public function __construct ()
    {
    	return $this->middleware('admin');

    	parent::__construct();
	}

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
    //     event(new TagsVerifiedByAdmin($photo->id));
    // }

    /**
     * The image and the tags are correct
     *
     * Updates Country, State + City table
     */
    public function verifykeepimage (Request $request)
    {
        $photo = Photo::find($request->photoId);
        $photo->verified = 2;
        $photo->verification = 1;
        $photo->save();

        // todo - dispatch via horizon
        event(new TagsVerifiedByAdmin($photo->id));
    }

    /**
     * Incorrect image - reset verification to 0
     */
    public function incorrect (Request $request)
    {
        $this->reset($request->photoId);

        $user = Auth::user();
        $user->count_correctly_verified = 0;
        $user->save();

        return ['success' => true];
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

        $categories = Photo::categories();

        foreach ($categories as $category)
        {
            if ($photo->$category)
            {
                // hold instance of the relationship to delete
                $d = $photo->$category;

                // remove the model from the photo
                $category_id = $category . '_id';
                $photo->$category_id = null;
                $photo->save();

                // delete the relationship
                $d->delete();
            }
        }

        // persist reset verification changes
        $photo->save();
    }

    /**
     * Delete an image and its records
     */
    public function destroy(Request $request)
    {
        $photo = Photo::find($request->photoId);

        try {
            if (app()->environment('production')) {
                $path = substr($photo->filename, 42);
                \Storage::disk('s3')->delete($path);
            } else {
                // Strip the app name from the filename
                // Resulting path is like 'local-uploads/2021/07/07/photo.jpg'
                $path = public_path(substr($photo->filename, strlen(config('app.url'))));

                if (File::exists($path)) {
                    File::delete($path);
                }
            }
            $photo->delete();
        } catch (Exception $e) {
            \Log::info(["Admin delete failed", $e]);
        }

        return ['success' => true];
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
        foreach ($request['categories'] as $category => $values)
        {
            $total = 0;
            foreach ($values as $item => $quantity)
            {
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

            }
        }

        $photo->total_litter = $litterTotal;
        $photo->save();

        // todo - horizon
        event(new TagsVerifiedByAdmin($photo->id));
    }


    /**
     * Verify the image
     * Keep the image
     * Image was not correctly inputted! LitterCorrectlyCount = 0.
     *
     * We need to Update the Country, State and City model
     * - remove previous tag counts
     * - add new tag counts
     */
    public function updateTags (Request $request)
    {
        $photo = Photo::find($request->photoId);
        $photo->verification = 1;
        $photo->verified = 2;
        $photo->total_litter = 0;
        $photo->save();

        $user = User::find($photo->user_id);
        $user->count_correctly_verified = 0; // At 100, the user earns a Littercoin
        $user->save();

        // this event is needed if the photo is already verified
        // event (new ResetTagsCountAdmin($photo->id));

        $this->addTags($request->tags, $request->photoId);

        // todo - dispatch event via horizon
        event (new TagsVerifiedByAdmin($photo->id));
    }

    /**
     * Get the next image to verify
     */
    public function getImage ()
    {
        if ($photo = Photo::where('verification', 0.1)->first())
        {
            $photo->tags();
        }

        else
        {
            $photo = Photo::where([
                'verification' => 0,
                 ['user_id', '!=', 3233] // dont load freds data
            ])->first();
        }

        // Count photos that have been uploaded, but not tagged or submitted for verification
        $photosNotProcessed = Photo::where([
                ['verification', 0],
                ['user_id', '!=', 3233]
        ])->count();

        // Count photos submitted for verification
        $photosAwaitingVerification = Photo::where([
            ['verified', '<', 2], // not verified
            ['verification', '>', 0], // submitted for verification
            ['user_id', '!=', 3233]
        ])->count();

        return [
            'photo' => $photo,
            'photosNotProcessed' => $photosNotProcessed,
            'photosAwaitingVerification' => $photosAwaitingVerification
        ];
    }
}

<?php

namespace App\Http\Controllers;

use Image;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Contracts\Filesystem\Filesystem;

class AWS3Controller extends Controller
{
    // public function imageUpload() {
    // 	return view('pages.aws.image');
    // }

  //   public function post(Request $request) {
  //   	// return $request; //validate, image/jpg, image/jpeg, .jpg, .JPG, .png, .jpeg .JPEG'

  //       // $this->validate($request, [
  //       //     'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
  //       // ]);

  //       $file = $request->file('image');

  //       dd($file);
  //       // return $image;

  //       $exif = Image::make($file->getRealPath());

  //       return $exif;

  //       $filename =  time() . '.' . $image->getClientOriginalExtension();

		// $s3 = \Storage::disk('s3');

		// $filePath = '/support-tickets/' . $filename;

		// $s3->put($filePath, file_get_contents($image), 'public');
		// // use streams instead of file_get_contents if > 10mb

		// $imageName = \Storage::disk(‘s3’)->url($imageName);

		// return ['success' => 'Done!'];
  //   }
}

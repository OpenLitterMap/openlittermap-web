<?php

namespace App\Actions\Photos;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Facades\Image;

class ResizePhotoAction
{
    public function run(UploadedFile $file): \Intervention\Image\Image
    {
        $image = Image::make($file);

        $image->resize(500, 500);

        $image->resize(500, 500, function ($constraint) {
            $constraint->aspectRatio();
        });

        return $image;
    }
}

<?php

namespace App\Actions\Photos;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Facades\Image;

class MakeImageAction
{
    /**
     * Create an instance of Intervention Image using an UploadedFile
     *
     * @param UploadedFile $file
     * @param bool $resize
     *
     * @return \Intervention\Image\Image
     */
    public function run (UploadedFile $file, bool $resize = false): \Intervention\Image\Image
    {
        $image = Image::make($file);

        if ($resize)
        {
            $image->resize(500, 500);

            $image->resize(500, 500, function ($constraint) {
                $constraint->aspectRatio();
            });
        }

        return $image;
    }
}

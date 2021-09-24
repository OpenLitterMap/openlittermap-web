<?php

namespace App\Actions\Photos;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
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
    public function run(UploadedFile $file, bool $resize = false): \Intervention\Image\Image
    {
        $image = $this->getImage($file);

        if ($resize) {
            $image->resize(500, 500);

            $image->resize(500, 500, function ($constraint) {
                $constraint->aspectRatio();
            });
        }

        return $image;
    }

    /**
     * @param UploadedFile $file
     * @return \Intervention\Image\Image
     */
    protected function getImage(UploadedFile $file): \Intervention\Image\Image
    {
        $extension = $file->getClientOriginalExtension();

        if (!in_array($extension, ['heif', 'heic'])) {
            return Image::make($file);
        }

        // Path for a temporary file from the upload -> storage/app/sample1.heic
        $tmpFilepath = storage_path('app/' . $file->getClientOriginalName());
        // Path for a converted temporary file -> storage/app/sample1.jpg
        $convertedFilepath = storage_path('app/' . str_replace(".$extension", '.jpg', $file->getClientOriginalName()));

        // Store the uploaded file on the server
        File::put($tmpFilepath, $file->getContent());

        // Run a shell command to execute ImageMagick conversion
        exec('magick convert ' . $tmpFilepath . ' ' . $convertedFilepath);

        // Run another shell command to copy the exif data
        exec('exiftool -overwrite_original_in_place -tagsFromFile ' . $tmpFilepath . ' ' . $convertedFilepath);

        // Make the image from the new converted file
        $image = Image::make($convertedFilepath);

        // Remove the temporary files from storage
        unlink($tmpFilepath);
        unlink($convertedFilepath);

        return $image;
    }
}

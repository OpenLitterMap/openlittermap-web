<?php

namespace App\Actions\Photos;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;

class MakeImageAction
{
    private const TEMP_HEIC_STORAGE_DIR = 'app/heic_images/';

    /**
     * Create an instance of Intervention Image using an UploadedFile
     *
     * @param UploadedFile $file
     * @param bool $resize
     *
     * @return array
     */
    public function run(UploadedFile $file, bool $resize = false): array
    {
        $imageAndExifData = $this->getImageAndExifData($file);

        if ($resize) {
            $imageAndExifData['image']->resize(500, 500);

            $imageAndExifData['image']->resize(500, 500, function ($constraint) {
                $constraint->aspectRatio();
            });
        }

        return $imageAndExifData;
    }

    /**
     * @param UploadedFile $file
     * @return array
     * @throws Exception
     */
    protected function getImageAndExifData(UploadedFile $file): array
    {
        $extension = $file->getClientOriginalExtension();

        // If the image is not type HEIC, HEIF
        // We can assume its jpg, png, and can be handled by the default GD image library
        // Otherwise, we are going to have to handle HEIC separately.
        if (!in_array(strtolower($extension), ['heif', 'heic'])) {
            $image = Image::make($file);
            $exif = $image->exif();

            return compact('image', 'exif');
        }

        // Generating a random filename, and not using the image's
        // original filename to handle cases
        // that contain spaces or other weird characters
        $randomFilename = bin2hex(random_bytes(8));

        // Path for a temporary file from the upload -> storage/app/heic_images/sample1.heic
        $tmpFilepath = storage_path(
            self::TEMP_HEIC_STORAGE_DIR .
            $randomFilename . ".$extension"
        );

        // Path for a converted temporary file -> storage/app/heic_images/sample1.jpg
        $convertedFilepath = storage_path(
            self::TEMP_HEIC_STORAGE_DIR .
            $randomFilename . '.jpg'
        );

        // Store the uploaded HEIC file on the server
        File::put($tmpFilepath, $file->getContent());

        // Run a shell command to execute ImageMagick conversion
        exec('magick convert ' . $tmpFilepath . ' ' . $convertedFilepath);

        // Make the image from the new converted file
        $image = Image::make($convertedFilepath);
        $exif = $image->exif();

        // Remove the temporary files from storage
        unlink($tmpFilepath);
        unlink($convertedFilepath);

        return compact('image', 'exif');
    }
}

<?php

namespace App\Actions\Photos;

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
     * @return array<\Intervention\Image\Image, array>
     */
    public function run (UploadedFile $file, bool $resize = false): array
    {
        $imageAndExifData = $this->getImageAndExifData($file);

        if ($resize)
        {
            $imageAndExifData['image']->resize(500, 500);

            $imageAndExifData['image']->resize(500, 500, function ($constraint) {
                $constraint->aspectRatio();
            });
        }

        return $imageAndExifData;
    }

    /**
     * @param UploadedFile $file
     * @return array<\Intervention\Image\Image, array>
     */
    protected function getImageAndExifData(UploadedFile $file): array
    {
        $extension = $file->getClientOriginalExtension();

        // If the image is not type HEIC, HEIF
        // We can assume its jpg, png, and can be handled by the default GD image library
        // Otherwise, we are going to have to handle HEIC separately.
        if (!in_array(strtolower($extension), ['heif', 'heic']))
        {
            $image = Image::make($file);
            $exif = $image->exif();

            return [
                'image' => $image,
                'exif' => $exif,
                'typeHeic' => false
            ];
        }

        // We need to convert

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
            $randomFilename . '.png'
        );

        // Store the uploaded HEIC file on the server
        File::put($tmpFilepath, $file->getContent());

        // Run a shell command to execute ImageMagick conversion
        exec('magick convert ' . $tmpFilepath . ' ' . $convertedFilepath);

//         Run another shell command to copy the exif data
//        exec('exiftool -overwrite_original_in_place -tagsFromFile ' . $tmpFilepath . ' ' . $convertedFilepath);

        // Give ourserlves an instance of image intervention using the newly converted png
        $image = Image::make($convertedFilepath);

        // Note: The EXIF is not being copied to the PNG therefore we read it from the HEIC.

        // Extract the coordinates
        $gpsString = "";
        $gpsArrayItems = [];

        // Todo - get phone / camera model name
        exec("exiftool $tmpFilepath -gpslatitude -gpslongitude -gpstimestamp -datetimeoriginal -n -json", $gpsArrayItems);

        foreach ($gpsArrayItems as $gpsArrayItem)
        {
            $gpsString .= $gpsArrayItem;
        }

        $exif = json_decode($gpsString, true)[0];

        // 'GPSLatitude' => 123,
        // 'GPSLongitude' => 456,
        // 'DateTimeOriginal' => '2021:10:23 12:33:35',

        // Temp fix
        $exif["Model"] = "iPhone";

        return [
            'image' => $image,
            'exif' => $exif,
            'typeHeic' => true,
            'tmpFilePath' => $tmpFilepath,
            'convertedFilePath' => $convertedFilepath
        ];
    }
}

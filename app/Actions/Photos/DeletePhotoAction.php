<?php


namespace App\Actions\Photos;


use App\Models\Photo;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DeletePhotoAction
{
    public function run(Photo $photo)
    {
        if (app()->environment('production')) {
            $this->deletePhotoOnProduction($photo);
        } else if (app()->environment('staging')) {
            $this->deletePhotoOnStaging($photo);
        } else {
            $this->deletePhoto($photo);
        }
    }

    /**
     * @param Photo $photo
     */
    protected function deletePhotoOnProduction(Photo $photo): void
    {
        $path = substr($photo->filename, 42);
        Storage::disk('s3')->delete($path);
    }

    /**
     * @param Photo $photo
     */
    protected function deletePhotoOnStaging(Photo $photo): void
    {
        $path = substr($photo->filename, 58);
        Storage::disk('staging')->delete($path);
    }

    /**
     * @param Photo $photo
     */
    protected function deletePhoto(Photo $photo): void
    {
        // Strip the app name from the filename
        // Resulting path is like 'local-uploads/2021/07/07/photo.jpg'
        $path = public_path(substr($photo->filename, strlen(config('app.url'))));

        if (File::exists($path)) {
            File::delete($path);
        }
    }
}

<?php

namespace App\Actions\Photos;

use App\Models\Photo;
use Illuminate\Support\Facades\Storage;

class DeletePhotoAction
{
    /**
     * Delete high-res and 500x500 photo
     *
     * @param Photo $photo
     */
    public function run (Photo $photo)
    {
        $this->deletePhoto($photo->filename, 's3');

        $this->deletePhoto($photo->five_hundred_square_filepath, 'bbox');
    }

    /**
     * Delete a photo from a specified disk
     *
     * @param string $filename
     * @param string $disk
     */
    protected function deletePhoto (string $filename, string $disk) :void
    {
        $path = str_replace(
            rtrim(Storage::disk($disk)->url('/'), '/'),
            '',
            $filename
        );

        Storage::disk($disk)->delete($path);
    }
}

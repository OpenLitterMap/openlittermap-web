<?php

namespace App\Actions\Photos;

use App\Models\Photo;
use Illuminate\Support\Facades\Storage;

class DeletePhotoAction
{
    /**
     * Delete high-res and 500x500 photo
     *
     * If somehow a photo does not contain a filename
     * we'll just assume the photo has already been deleted
     * or has been partially uploaded, in which case there's nothing to delete.
     * That's why we're not throwing an exception here
     *
     * @param Photo $photo
     */
    public function run (Photo $photo)
    {
        if ($photo->filename) {
            $this->deletePhoto($photo->filename, 's3');
        }

        if ($photo->five_hundred_square_filepath) {
            $this->deletePhoto($photo->five_hundred_square_filepath, 'bbox');
        }
    }

    /**
     * Delete a photo from a specified disk
     *
     * @param string $filename
     * @param string $disk
     */
    protected function deletePhoto (string $filename, string $disk) :void
    {
        // Remove storage prefix if found. Only needed for local testing.
        $filename = ltrim($filename, '/storage/');

        if (Storage::disk($disk)->exists($filename)) {
            Storage::disk($disk)->delete($filename);
        }
    }
}

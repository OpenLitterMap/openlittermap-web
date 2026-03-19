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
     */
    protected function deletePhoto(string $filename, string $disk): void
    {
        // Use a placeholder to avoid AWS SDK crash on empty key with url('')
        $baseUrl = str_replace('__placeholder__', '', Storage::disk($disk)->url('__placeholder__'));

        if (str_starts_with($filename, $baseUrl)) {
            // Full URL matching this disk — strip the base to get the S3 key
            $path = substr($filename, strlen($baseUrl));
        } elseif (! str_starts_with($filename, 'http://') && ! str_starts_with($filename, 'https://')) {
            // Relative path (no URL prefix) — use as-is
            $path = $filename;
        } else {
            // Full URL belonging to a different host (e.g., production S3 on local dev) — skip
            return;
        }

        if ($path === '' || $path === false) {
            return;
        }

        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}

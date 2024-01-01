<?php

namespace App\Actions\Photos;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Image;

class UploadPhotoAction
{
    /**
     * Upload photo to a specific disk
     *
     *
     */
    public function run (Image $photo, Carbon $datetime, string $filename, string $disk = 's3'): string
    {
        $path = $this->extractPath($datetime, $filename);

        $filesystem = Storage::disk($disk);

        $filesystem->put($path, $photo->stream(), 'public');

        return $filesystem->url($path);
    }

    /**
     * Get the path for the image
     *
     * eg: /yyyy/mm/dd/filepath.png
     *
     *
     */
    protected function extractPath (Carbon $datetime, string $filename): string
    {
        // Create dir/filename
        $explode = explode('-', $datetime->format('Y-m-d H:i:s'));
        $y = $explode[0];
        $m = $explode[1];
        $d = substr($explode[2], 0, 2);

        return $y . '/' . $m . '/' . $d . '/' . $filename;
    }
}

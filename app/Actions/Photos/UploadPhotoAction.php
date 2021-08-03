<?php


namespace App\Actions\Photos;


use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Image;

class UploadPhotoAction
{
    public function run(Image $photo, string $filename, Carbon $datetime): string
    {
        $path = $this->extractPath($datetime, $filename);

        $s3 = Storage::disk('s3');

        $s3->put($path, $photo->stream(), 'public');

        return $s3->url($path);
    }

    /**
     * @param Carbon $datetime
     * @param string $filename
     * @return string
     */
    protected function extractPath(Carbon $datetime, string $filename): string
    {
        // Create dir/filename
        $explode = explode('-', $datetime->format('Y-m-d H:i:s'));
        $y = $explode[0];
        $m = $explode[1];
        $d = substr($explode[2], 0, 2);

        return $y . '/' . $m . '/' . $d . '/' . $filename;
    }
}

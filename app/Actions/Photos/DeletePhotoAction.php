<?php


namespace App\Actions\Photos;


use App\Models\Photo;
use Illuminate\Support\Facades\Storage;

class DeletePhotoAction
{
    public function run(Photo $photo)
    {
        $path = str_replace(
            rtrim(Storage::disk('s3')->url('/'), '/'),
            '',
            $photo->filename
        );

        Storage::disk('s3')->delete($path);
    }
}

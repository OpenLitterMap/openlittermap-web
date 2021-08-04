<?php


namespace App\Actions\Photos;


use App\Models\Photo;
use Illuminate\Support\Facades\Storage;

class DeletePhotoAction
{
    public function run(Photo $photo)
    {
        $this->deletePhoto($photo->filename, 's3');

        $this->deletePhoto($photo->five_hundred_square_filepath, 'bbox');
    }

    /**
     * @param string $filename
     * @param string $disk
     */
    protected function deletePhoto(string $filename, string $disk): void
    {
        $path = str_replace(
            rtrim(Storage::disk($disk)->url('/'), '/'),
            '',
            $filename
        );

        Storage::disk($disk)->delete($path);
    }
}

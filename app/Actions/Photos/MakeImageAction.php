<?php

namespace App\Actions\Photos;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Facades\Image;

/**
 * @deprecated - find another way to convert HEIC
 */
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
     * @throws Exception
     */
    public function run (UploadedFile $file, bool $resize = false): array
    {
        $imageAndExifData = $this->getImageAndExifData($file);

        if ($resize) {
            $imageAndExifData['image']->resize(500, 500);
        }

        return $imageAndExifData;
    }

    /**
     * Check if the file is HEIC/HEIF by extension or MIME type.
     *
     * iOS may send HEIC files with a .jpg extension, so we check both.
     */
    protected function isHeic(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = strtolower($file->getMimeType());

        return in_array($extension, ['heif', 'heic'])
            || in_array($mimeType, ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence']);
    }

    /**
     * @param UploadedFile $file
     * @return array
     * @throws Exception
     */
    protected function getImageAndExifData (UploadedFile $file): array
    {
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getMimeType();
        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $realPath = $file->getRealPath();

        Log::info('MakeImageAction: processing upload', [
            'original_name' => $originalName,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'real_path' => $realPath,
            'is_valid' => $file->isValid(),
        ]);

        // Check both extension AND mime type for HEIC detection
        if (!$this->isHeic($file)) {
            try {
                $image = Image::make($file)->orientate();
                $exif = $image->exif();

                return compact('image', 'exif');
            } catch (Exception $e) {
                Log::error('MakeImageAction: Image::make failed', [
                    'original_name' => $originalName,
                    'extension' => $extension,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        Log::info('MakeImageAction: HEIC detected, converting via ImageMagick', [
            'original_name' => $originalName,
            'extension' => $extension,
            'mime_type' => $mimeType,
        ]);

        // Generating a random filename, and not using the image's
        // original filename to handle cases
        // that contain spaces or other weird characters
        $randomFilename = bin2hex(random_bytes(8));

        // Ensure temp directory exists
        $tempDir = storage_path(self::TEMP_HEIC_STORAGE_DIR);
        if (!File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        // Path for a temporary file from the upload -> storage/app/heic_images/sample1.heic
        $tmpFilepath = storage_path(
            self::TEMP_HEIC_STORAGE_DIR .
            $randomFilename . '.heic'
        );

        // Path for a converted temporary file -> storage/app/heic_images/sample1.jpg
        $convertedFilepath = storage_path(
            self::TEMP_HEIC_STORAGE_DIR .
            $randomFilename . '.jpg'
        );

        // Store the uploaded HEIC file on the server
        File::put($tmpFilepath, $file->getContent());

        // Run a shell command to execute ImageMagick conversion
        $output = [];
        $returnCode = 0;
        exec('magick convert ' . escapeshellarg($tmpFilepath) . ' ' . escapeshellarg($convertedFilepath) . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($convertedFilepath)) {
            Log::error('MakeImageAction: ImageMagick HEIC conversion failed', [
                'original_name' => $originalName,
                'return_code' => $returnCode,
                'output' => implode("\n", $output),
                'tmp_exists' => file_exists($tmpFilepath),
                'converted_exists' => file_exists($convertedFilepath),
            ]);

            // Clean up temp file
            if (file_exists($tmpFilepath)) {
                unlink($tmpFilepath);
            }

            throw new Exception('Failed to convert HEIC image. ImageMagick returned code ' . $returnCode);
        }

        Log::info('MakeImageAction: HEIC conversion successful', [
            'converted_size' => filesize($convertedFilepath),
        ]);

        // Make the image from the new converted file
        $image = Image::make($convertedFilepath)->orientate();
        $exif = $image->exif();

        // Remove the temporary files from storage
        unlink($tmpFilepath);
        unlink($convertedFilepath);

        return compact('image', 'exif');
    }
}

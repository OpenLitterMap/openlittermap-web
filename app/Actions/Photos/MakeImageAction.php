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
     * Check if the file is HEIC/HEIF by extension, MIME type, or magic bytes.
     *
     * iOS often sends HEIC files with a .jpg extension and image/jpeg MIME,
     * so we also inspect the ftyp box in the file header.
     */
    protected function isHeic(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = strtolower($file->getMimeType());

        if (in_array($extension, ['heif', 'heic'])
            || in_array($mimeType, ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'])) {
            return true;
        }

        // Check ftyp box magic bytes — handles iOS HEIC disguised as JPEG
        return $this->hasHeicMagicBytes($file->getRealPath());
    }

    /**
     * Read the ISO BMFF ftyp box to detect HEIC/HEIF containers.
     * Bytes 4-7 = "ftyp", bytes 8-11 = brand code.
     */
    protected function hasHeicMagicBytes(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 12);
        fclose($handle);

        if (strlen($header) < 12) {
            return false;
        }

        // ftyp marker at bytes 4-7
        if (substr($header, 4, 4) !== 'ftyp') {
            return false;
        }

        // Brand code at bytes 8-11
        $brand = substr($header, 8, 4);
        return in_array($brand, ['heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'mif1', 'msf1']);
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

        // Check extension, MIME type, AND magic bytes for HEIC detection
        if (!$this->isHeic($file)) {
            try {
                $image = Image::make($file)->orientate();
                $exif = $image->exif();

                return compact('image', 'exif');
            } catch (Exception $e) {
                // Last resort: try ImageMagick conversion in case HEIC detection missed it
                Log::warning('MakeImageAction: Image::make failed, attempting ImageMagick fallback', [
                    'original_name' => $originalName,
                    'extension' => $extension,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'error' => $e->getMessage(),
                ]);

                try {
                    return $this->convertViaImageMagick($file->getRealPath(), $originalName, $extension, $mimeType);
                } catch (Exception $fallbackException) {
                    // Both paths failed — throw the original error
                    throw $e;
                }
            }
        }

        Log::info('MakeImageAction: HEIC detected, converting via ImageMagick', [
            'original_name' => $originalName,
            'extension' => $extension,
            'mime_type' => $mimeType,
        ]);

        return $this->convertViaImageMagick($file->getRealPath(), $originalName, $extension, $mimeType);
    }

    /**
     * Convert an image to JPEG via ImageMagick shell command.
     *
     * @throws Exception
     */
    protected function convertViaImageMagick(string $sourcePath, string $originalName, string $extension, string $mimeType): array
    {
        $randomFilename = bin2hex(random_bytes(8));

        $tempDir = storage_path(self::TEMP_HEIC_STORAGE_DIR);
        if (!File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $tmpFilepath = storage_path(self::TEMP_HEIC_STORAGE_DIR . $randomFilename . '.heic');
        $convertedFilepath = storage_path(self::TEMP_HEIC_STORAGE_DIR . $randomFilename . '.jpg');

        try {
            copy($sourcePath, $tmpFilepath);

            $output = [];
            $returnCode = 0;
            exec('magick convert ' . escapeshellarg($tmpFilepath) . ' ' . escapeshellarg($convertedFilepath) . ' 2>&1', $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($convertedFilepath)) {
                Log::error('MakeImageAction: ImageMagick conversion failed', [
                    'original_name' => $originalName,
                    'return_code' => $returnCode,
                    'output' => implode("\n", $output),
                ]);

                throw new Exception('Failed to convert image. ImageMagick returned code ' . $returnCode);
            }

            Log::info('MakeImageAction: ImageMagick conversion successful', [
                'original_name' => $originalName,
                'converted_size' => filesize($convertedFilepath),
            ]);

            $image = Image::make($convertedFilepath)->orientate();
            $exif = $image->exif();

            return compact('image', 'exif');
        } finally {
            // Always clean up temp files
            if (file_exists($tmpFilepath)) {
                @unlink($tmpFilepath);
            }
            if (file_exists($convertedFilepath)) {
                @unlink($convertedFilepath);
            }
        }
    }
}

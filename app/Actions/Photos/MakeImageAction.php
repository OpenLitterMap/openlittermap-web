<?php

namespace App\Actions\Photos;

use App\Exceptions\HeicConversionException;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Intervention\Image\Facades\Image;

class MakeImageAction
{
    private const TEMP_HEIC_STORAGE_DIR = 'app/heic_images/';

    private const FAILED_HEIC_STORAGE_DIR = 'app/heic_failed/';

    private const HEIC_CONVERT_TIMEOUT = 60;

    private const JPEG_QUALITY = 92;

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
    public function isHeic(UploadedFile $file): bool
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

        // Check extension, MIME type, AND magic bytes for HEIC detection
        if (!$this->isHeic($file)) {
            try {
                $image = Image::make($file)->orientate();
                $exif = $image->exif();

                return compact('image', 'exif');
            } catch (Exception $e) {
                // Last resort: try heif-convert in case HEIC detection missed it
                Log::warning('MakeImageAction: Image::make failed, attempting heif-convert fallback', [
                    'original_name' => $originalName,
                    'extension' => $extension,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'error' => $e->getMessage(),
                ]);

                try {
                    return $this->convertViaHeifConvert($file->getRealPath(), $originalName, $extension, $mimeType);
                } catch (Exception $fallbackException) {
                    // Both paths failed — throw the original error
                    throw $e;
                }
            }
        }

        Log::info('MakeImageAction: HEIC detected, converting via heif-convert', [
            'original_name' => $originalName,
            'extension' => $extension,
            'mime_type' => $mimeType,
        ]);

        return $this->convertViaHeifConvert($file->getRealPath(), $originalName, $extension, $mimeType);
    }

    /**
     * Convert an image to JPEG via `heif-convert` (from libheif-examples).
     *
     * libheif decodes HEIC variants that the ImageMagick 6 HEIC delegate rejects
     * (newer iOS/Xiaomi brands). `heif-convert` embeds EXIF/GPS, XMP and ICC into
     * the output JPEG by default and bakes orientation (rewriting EXIF Orientation
     * to 1), so the downstream `->orientate()` becomes a harmless no-op. The output
     * path is deterministic for single-image HEICs; multi-image HEICs would not
     * produce the expected file and are caught by the `!file_exists()` guard.
     *
     * On any failure (non-zero exit or missing output) the source HEIC is MOVED to
     * storage/app/heic_failed/ as a diagnostic sample before throwing.
     *
     * @return array{image: \Intervention\Image\Image, exif: array|null}
     * @throws HeicConversionException
     */
    protected function convertViaHeifConvert(string $sourcePath, string $originalName, string $extension, string $mimeType): array
    {
        $randomFilename = bin2hex(random_bytes(8));

        $tempDir = storage_path(self::TEMP_HEIC_STORAGE_DIR);
        if (!File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $tmpFilepath = storage_path(self::TEMP_HEIC_STORAGE_DIR . $randomFilename . '.heic');
        $convertedFilepath = storage_path(self::TEMP_HEIC_STORAGE_DIR . $randomFilename . '.jpg');
        $preservedPath = null;

        try {
            copy($sourcePath, $tmpFilepath);

            try {
                $result = Process::timeout(self::HEIC_CONVERT_TIMEOUT)->run([
                    config('services.heif_convert.path'),
                    '-q', (string) self::JPEG_QUALITY,
                    $tmpFilepath,
                    $convertedFilepath,
                ]);
            } catch (ProcessTimedOutException $e) {
                // A timeout THROWS before $result exists — preserve the sample here
                // too, otherwise the failure block below never runs and we 500.
                $preservedPath = $this->preserveFailedHeic($tmpFilepath, $randomFilename);

                Log::error('MakeImageAction: heif-convert timed out', [
                    'original_name' => $originalName,
                    'timeout' => self::HEIC_CONVERT_TIMEOUT,
                    'preserved_path' => $preservedPath,
                ]);

                throw new HeicConversionException('heif-convert timed out after ' . self::HEIC_CONVERT_TIMEOUT . 's', 0, $e);
            }

            if ($result->failed() || !file_exists($convertedFilepath)) {
                $preservedPath = $this->preserveFailedHeic($tmpFilepath, $randomFilename);

                Log::error('MakeImageAction: heif-convert conversion failed', [
                    'original_name' => $originalName,
                    'exit_code' => $result->exitCode(),
                    'output' => trim($result->output() . "\n" . $result->errorOutput()),
                    'preserved_path' => $preservedPath,
                ]);

                throw new HeicConversionException('Failed to convert image. heif-convert returned code ' . $result->exitCode());
            }

            Log::info('MakeImageAction: heif-convert conversion successful', [
                'original_name' => $originalName,
                'converted_size' => filesize($convertedFilepath),
            ]);

            $image = Image::make($convertedFilepath)->orientate();
            $exif = $image->exif();

            return compact('image', 'exif');
        } finally {
            // Clean up temp files — but never delete a preserved diagnostic sample.
            if ($preservedPath === null && file_exists($tmpFilepath)) {
                @unlink($tmpFilepath);
            }
            // heif-convert can emit suffixed outputs ({hash}-1.jpg, …) for
            // multi-image HEICs — remove every conversion output for this
            // basename, not just the exact expected path.
            foreach (File::glob(storage_path(self::TEMP_HEIC_STORAGE_DIR . $randomFilename . '*.jpg')) ?: [] as $leftover) {
                @unlink($leftover);
            }
        }
    }

    /**
     * Move an unconvertible HEIC to storage/app/heic_failed/ for diagnosis.
     * Every file here is a sample of a HEIC variant the server could not decode.
     */
    protected function preserveFailedHeic(string $tmpFilepath, string $hash): string
    {
        $failedDir = storage_path(self::FAILED_HEIC_STORAGE_DIR);
        if (!File::isDirectory($failedDir)) {
            File::makeDirectory($failedDir, 0755, true);
        }

        $failedFilepath = storage_path(self::FAILED_HEIC_STORAGE_DIR . $hash . '.heic');
        @rename($tmpFilepath, $failedFilepath);

        return $failedFilepath;
    }
}

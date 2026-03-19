<?php

namespace App\Http\Controllers\Photos;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PhotoSignedUrlController extends Controller
{
    private const URL_TTL_MINUTES = 5;
    private const WAITING_IMAGE = '/assets/images/waiting.png';

    /**
     * GET /api/photos/{id}/signed-url
     *
     * Returns a time-limited signed S3 URL for a public photo.
     * Rate-limited and origin-restricted to openlittermap.com.
     */
    public function __invoke(int $id): JsonResponse
    {
        // Origin check — only allow requests from openlittermap.com
        $origin = request()->header('Origin') ?? request()->header('Referer') ?? '';

        if (! $this->isAllowedOrigin($origin)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $photo = Photo::where('is_public', true)->find($id);

        if (! $photo) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // Only serve actual photo URL for verified (approved) photos
        if ($photo->verified->value >= VerificationStatus::ADMIN_APPROVED->value && $photo->filename) {
            $url = $this->generateSignedUrl($photo->filename);
        } else {
            $url = self::WAITING_IMAGE;
        }

        return response()->json([
            'url' => $url,
            'expires_in' => self::URL_TTL_MINUTES * 60,
        ]);
    }

    private function generateSignedUrl(string $filename): string
    {
        // Extract the S3 key from the full URL
        $disk = Storage::disk('s3');
        $baseUrl = str_replace('__placeholder__', '', $disk->url('__placeholder__'));
        $path = str_replace($baseUrl, '', $filename);
        $path = ltrim($path, '/');

        // Generate a temporary signed URL
        try {
            return $disk->temporaryUrl($path, now()->addMinutes(self::URL_TTL_MINUTES));
        } catch (\RuntimeException $e) {
            // Fallback for local dev / MinIO without signed URL support
            return $filename;
        }
    }

    private function isAllowedOrigin(string $origin): bool
    {
        $allowed = [
            'https://openlittermap.com',
            'https://www.openlittermap.com',
        ];

        // Allow localhost in non-production environments
        if (! app()->isProduction()) {
            $allowed[] = 'http://localhost';
            $allowed[] = 'https://localhost';
            $allowed[] = 'http://127.0.0.1';
            $allowed[] = 'https://olm.test';
        }

        foreach ($allowed as $domain) {
            if (str_starts_with($origin, $domain)) {
                return true;
            }
        }

        return false;
    }
}

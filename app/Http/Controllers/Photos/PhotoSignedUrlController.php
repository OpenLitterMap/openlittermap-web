<?php

namespace App\Http\Controllers\Photos;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
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
        if (! $this->isAllowedOrigin()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $photo = Photo::where('is_public', true)->find($id);

        if (! $photo) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // Only serve actual photo URL for verified (approved) photos
        if ($photo->verified->value >= VerificationStatus::ADMIN_APPROVED->value && $photo->filename) {
            $url = $this->generateSignedUrl($photo->filename);

            if (! $url) {
                return response()->json(['error' => 'Image unavailable'], 503);
            }
        } else {
            $url = self::WAITING_IMAGE;
        }

        return response()->json([
            'url' => $url,
            'expires_in' => self::URL_TTL_MINUTES * 60,
        ]);
    }

    private function generateSignedUrl(string $filename): ?string
    {
        // Local dev with production dataset: return the full URL directly
        if (! app()->isProduction() && filter_var($filename, FILTER_VALIDATE_URL)) {
            return $filename;
        }

        $disk = Storage::disk('s3');
        $baseUrl = str_replace('__placeholder__', '', $disk->url('__placeholder__'));
        $path = str_replace($baseUrl, '', $filename);
        $path = ltrim($path, '/');

        // Local dev with MinIO-relative paths: serve directly
        if (! app()->isProduction()) {
            return $disk->url($path);
        }

        try {
            return $disk->temporaryUrl($path, now()->addMinutes(self::URL_TTL_MINUTES));
        } catch (\RuntimeException $e) {
            Log::warning('Failed to generate signed URL', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function isAllowedOrigin(): bool
    {
        $origin = request()->header('Origin') ?? '';
        $referer = request()->header('Referer') ?? '';

        $allowed = [
            'https://openlittermap.com',
            'https://www.openlittermap.com',
        ];

        if (! app()->isProduction()) {
            $allowed[] = 'http://localhost';
            $allowed[] = 'https://localhost';
            $allowed[] = 'http://127.0.0.1';
            $allowed[] = 'https://olm.test';
        }

        // Check Origin header (exact match — no prefix matching)
        if ($origin !== '' && in_array(rtrim($origin, '/'), $allowed, true)) {
            return true;
        }

        // Fall back to Referer: extract scheme + host
        if ($referer !== '') {
            $parsed = parse_url($referer);
            $refererOrigin = ($parsed['scheme'] ?? '') . '://' . ($parsed['host'] ?? '');

            return in_array($refererOrigin, $allowed, true);
        }

        return false;
    }
}

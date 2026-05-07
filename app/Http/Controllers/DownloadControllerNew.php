<?php

namespace App\Http\Controllers;

use App\Exports\CreateCSVExport;
use App\Jobs\EmailUserExportCompleted;

use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Location\City;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DownloadControllerNew extends Controller
{
    /**
     * Download data for a location.
     *
     * Auth required (route lives in the auth:sanctum group). Guests previously
     * supplied an email field; that path was removed because (a) anonymous
     * exports are an abuse vector against the queue + S3 + outbound mail, and
     * (b) the auth-only path lets the rate limiter key on user_id alone.
     *
     * @location_type $string = 'country', 'state', or 'city'
     * @country = 'Ireland'
     * @state = 'County Cork'
     * @city = 'Cork'
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $locationName = match ($request->locationType) {
            'city'    => City::find($request->locationId)?->city,
            'state'   => State::find($request->locationId)?->state,
            'country' => Country::find($request->locationId)?->country,
            default   => null,
        };

        if ($locationName === null) {
            return ['success' => false, 'message' => 'location-not-found'];
        }

        $location_id = (int) $request->locationId;

        $formats = CreateCSVExport::parseFormats($request->input('format'));
        $layout = CreateCSVExport::parseLayout($request->input('layout'));

        // Pin one $now so timestamp + Y-m-d_His + Y/m/d can't disagree across a second boundary.
        $now = now();
        $fileSuffix = '_OpenLitterMap_' . CreateCSVExport::layoutSlug($layout)
            . '_' . $now->format('Y-m-d_His')
            . '_u' . $user->id
            . '.csv';

        // Slug the location name to prevent unexpected path segments from DB-sourced strings
        // (S3 keys are flat strings so traversal can't escape, but `..` produces malformed keys).
        // Str::slug() returns '' for all-non-Latin names (中国, العربية, Ελλάδα, …) — fall back
        // to a typed identifier so the file stays distinguishable in S3 listings.
        $locationSlug = Str::slug($locationName) ?: ($request->locationType . '-' . $location_id);
        $path = $now->format('Y/m/d') . '/' . $now->timestamp . '/' . $locationSlug . $fileSuffix;

        try {
            /* Dispatch job to create CSV file for export */
            (new CreateCSVExport($request->locationType, $location_id, null, null, [], [], $formats, $layout))
                ->notifyOnFailure($user->email)
                ->queue($path, 's3', null, ['visibility' => 'public'])
                ->chain([
                    new EmailUserExportCompleted($user->email, $path)
                ]);

            return ['success' => true];
        } catch (Exception $e) {
            Log::error('download failed', ['error' => $e->getMessage()]);

            return ['success' => false];
        }
    }
}

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
     * Download data for a location
     *
     * @location_type $string = 'country', 'state', or 'city'
     * @country = 'Ireland'
     * @state = 'County Cork'
     * @city = 'Cork'
     */
    public function index (Request $request)
    {
        $authUser = auth()->user();

        // Guests must supply a syntactically valid email; rate-limit also keys on it.
        if (is_null($authUser)) {
            $request->validate(['email' => 'required|email:rfc']);
            $email = $request->email;
        } else {
            $email = $authUser->email;
        }

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
        // Random suffix for guests prevents same-second collisions; auth users get a stable id.
        $userSlug = $authUser ? '_u' . $authUser->id : '_g' . Str::random(6);
        $fileSuffix = '_OpenLitterMap_' . CreateCSVExport::layoutSlug($layout)
            . '_' . $now->format('Y-m-d_His')
            . $userSlug
            . '.csv';

        // Slug the location name to prevent unexpected path segments from DB-sourced strings
        // (S3 keys are flat strings so traversal can't escape, but `..` produces malformed keys).
        $path = $now->format('Y/m/d') . '/' . $now->timestamp . '/' . Str::slug($locationName) . $fileSuffix;

        try
        {
            /* Dispatch job to create CSV file for export */
            (new CreateCSVExport($request->locationType, $location_id, null, null, [], [], $formats, $layout))
                ->notifyOnFailure($email)
                ->queue($path, 's3', null, ['visibility' => 'public'])
                ->chain([
                    new EmailUserExportCompleted($email, $path)
                ]);

            return ['success' => true];
        }

        catch (Exception $e)
        {
            Log::error('download failed', ['error' => $e->getMessage()]);

            return ['success' => false];
        }
    }
}

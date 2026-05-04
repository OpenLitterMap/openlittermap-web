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

        $unix = now()->timestamp;
        $userSlug = $authUser ? '_u' . $authUser->id : '_uguest';
        $fileSuffix = '_OpenLitterMap_' . CreateCSVExport::layoutSlug($layout)
            . '_' . now()->format('Y-m-d_His')
            . $userSlug
            . '.csv';

        $path = now()->format('Y/m/d') . '/' . $unix . '/' . $locationName . $fileSuffix;

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

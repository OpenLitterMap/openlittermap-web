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
        $email = (is_null(auth()->user()))
            ? $request->email
            : auth()->user()->email;

        $x     = new \DateTime();
        $date  = $x->format('Y-m-d');
        $date  = explode('-', $date);
        $year  = $date[0];
        $month = $date[1];
        $day   = $date[2];
        $unix  = now()->timestamp;

        $path = $year.'/'.$month.'/'.$day.'/'.$unix.'/';  // 2020/10/25/unix/
        $location_id = 0;

        $formats = CreateCSVExport::parseFormats($request->input('format'));
        $layout = CreateCSVExport::parseLayout($request->input('layout'));
        $fileSuffix = '_OpenLitterMap_' . CreateCSVExport::layoutSlug($layout) . '_' . now()->format('Y-m-d_His') . '.csv';

        try
        {
            if ($request->locationType === 'city')
            {
                if ($city = City::find($request->locationId))
                {
                    $path .= $city->city . $fileSuffix;
                    $location_id = $city->id;
                }
            }
            else if ($request->locationType === 'state')
            {
                if ($state = State::find($request->locationId))
                {
                    $path .= $state->state . $fileSuffix;
                    $location_id = $state->id;
                }
            }
            else if ($request->locationType === 'country')
            {
                if ($country = Country::find($request->locationId))
                {
                    $path .= $country->country . $fileSuffix;
                    $location_id = $country->id;
                }
            }

            /* Dispatch job to create CSV file for export */
            (new CreateCSVExport($request->locationType, $location_id, null, null, [], [], $formats, $layout))
                ->notifyOnFailure($email)
                ->queue($path, 's3', null, ['visibility' => 'public'])
                ->chain([
                    // These jobs are executed when above is finished.
                    new EmailUserExportCompleted($email, $path)
                    // new ....job
                ]);

            return ['success' => true];
        }

        catch (Exception $e)
        {
            Log::info(['download failed', $e->getMessage()]);

            return ['success' => false];
        }
    }
}

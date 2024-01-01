<?php

namespace App\Http\Controllers;

use DateTime;
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

        $x     = new DateTime();
        $date  = $x->format('Y-m-d');
        $date  = explode('-', $date);

        $year  = $date[0];
        $month = $date[1];
        $day   = $date[2];
        $unix  = now()->timestamp;

        $path = $year.'/'.$month.'/'.$day.'/'.$unix.'/';  // 2020/10/25/unix/
        $location_id = 0;

        try
        {
            if ($request->locationType === 'city') {
                if ($city = City::find($request->locationId))
                {
                    $path .= $city->city . '_OpenLitterMap.csv';
                    $location_id = $city->id;
                }
            } elseif ($request->locationType === 'state') {
                if ($state = State::find($request->locationId))
                {
                    $path .= $state->state . '_OpenLitterMap.csv';
                    $location_id = $state->id;
                }
            } elseif ($request->locationType === 'country') {
                if ($country = Country::find($request->locationId))
                {
                    $path .= $country->country . '_OpenLitterMap.csv';
                    $location_id = $country->id;
                }
            }

            /* Dispatch job to create CSV file for export */
            (new CreateCSVExport($request->locationType, $location_id))
                ->queue($path, 's3', null, ['visibility' => 'public'])
                ->chain([
                    // These jobs are executed when above is finished.
                    new EmailUserExportCompleted($email, $path)
                    // new ....job
                ]);

            return ['success' => true];
        }

        catch (Exception $exception)
        {
            Log::info(['download failed', $exception->getMessage()]);

            return ['success' => false];
        }
    }
}

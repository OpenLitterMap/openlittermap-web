<?php

namespace App\Http\Controllers;

use App\Exports\CreateCSVExport;
use App\Jobs\EmailUserExportCompleted;

use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Location\City;

use Illuminate\Http\Request;

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
        // If there is no user logged in, then use the email address in $request:
        if(is_null(auth()->user()))
        {
            $email = $request->email;
        }

        // If the user is logged in, use their registered email address:
        else
        {
            $email = auth()->user()->email;
        }

        $x     = new \DateTime();
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
            if ($request->type === 'city')
            {
                if ($city = City::find($request->locationId))
                {
                    $path .= $city->city . '_OpenLitterMap.csv';
                    $location_id = $city->id;
                }
            }

            else if ($request->type === 'state')
            {
                if ($state = State::find($request->locationId))
                {
                    $path .= $state->state . '_OpenLitterMap.csv';
                    $location_id = $state->id;
                }
            }

            else if ($request->type === 'country')
            {
                if ($country = Country::find($request->locationId))
                {
                    $path .= $country->country . '_OpenLitterMap.csv';
                    $location_id = $country->id;
                }
            }

            /* Dispatch job to create CSV file for export */
            (new CreateCSVExport($request->type, $location_id))
                ->queue($path, 's3', null, ['visibility' => 'public'])
                ->chain([
                    // These jobs are executed when above is finished.
                    new EmailUserExportCompleted($email, $path)
                    // new ....job
                ]);

            return ['success' => true];
        }

        catch (\Exception $e)
        {
            \Log::info(['download failed', $e->getMessage()]);

            return ['success' => false];
        }
    }
}

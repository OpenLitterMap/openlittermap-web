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
        // \Log::info(['download', $request->all()]);

        $email = auth()->user()->email;

        $x     = new \DateTime();
        $date  = $x->format('Y-m-d');
        $date  = explode('-', $date);
        $year  = $date[0];
        $month = $date[1];
        $day   = $date[2];
        $unix  = now()->timestamp;

        $path = $year.'/'.$month.'/'.$day.'/'.$unix.'/';  // 2020/10/25/unix/

        $country_id = null;
        $state_id = null;
        $city_id = null;

        try
        {
            $country_id = Country::where('country', $request->country)
                ->orWhere('countrynameb', $request->country)
                ->orWhere('countrynamec', $request->country)
                ->first()
                ->id;

            if ($request->type === 'country')
            {
                $path .= $request->country.'_OpenLitterMap.csv';
            }

            else if ($request->type === 'state')
            {
                $path .= $request->country.'/'.$request->state.'_OpenLitterMap.csv';

                $state_id = State::where(['state' => $request->state, 'country_id' => $country_id])
                    ->orWhere(['statenameb' => $request->state, 'country_id' => $country_id])
                    ->first()
                    ->id;
            }

            else if ($request->type === 'city')
            {
                $path .= $request->country.'/'.$request->state.'/'.$request->city.'_OpenLitterMap.csv';

                $city_id = City::where(['city', $request->city, 'country_id', $country_id])
                    ->first()
                    ->id;
            }

            (new CreateCSVExport($country_id, $state_id, $city_id))
                ->queue($path, 's3', null, ['visibility' => 'public'])
                ->chain([
                    // These jobs are executed when above is finished.
                    new EmailUserExportCompleted($email, $path)
                    // new ....job
                ]);
            // ->allOnQueue('exports');

            return ['success' => true];
        }

        catch (\Exception $e)
        {
            \Log::info(['download failed', $e->getMessage()]);

            return ['success' => false];
        }
    }
}

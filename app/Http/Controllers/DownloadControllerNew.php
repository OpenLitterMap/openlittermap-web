<?php

namespace App\Http\Controllers;

use App\Exports\CreateCSVExport;
use App\Jobs\EmailUserExportCompleted;

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
        \Log::info(['download', $request->all()]);

        $email = auth()->user()->email;

        $x     = new \DateTime();
        $date  = $x->format('Y-m-d');
        $date  = explode('-', $date);
        $year  = $date[0];
        $month = $date[1];
        $day   = $date[2];
        $unix  = now()->timestamp;

        $path = $year.'/'.$month.'/'.$day.'/'.$unix.'/';  // 2020/10/25/unix/

        if ($request->type === 'country')
        {
            $path .= $request->country.'_OpenLitterMap.csv';
        }

        else if ($request->type === 'state')
        {
            $path .= $request->country.'/'.$request->state.'_OpenLitterMap.csv';
        }

        else if ($request->type === 'city')
        {
            $path .= $request->country.'/'.$request->state.'/'.$request->city.'_OpenLitterMap.csv';
        }


        (new CreateCSVExport($email))->queue($path, 's3')
            ->chain([
                // These jobs are executed when above is finished.
                new EmailUserExportCompleted($email, $path)
                // new ....job
            ]);
//        ->allOnQueue('exports');

        //\Log::info(['csv']);
    }
}

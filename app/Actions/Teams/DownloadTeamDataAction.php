<?php

namespace App\Actions\Teams;

use App\Exports\CreateCSVExport;
use App\Jobs\EmailUserExportCompleted;
use App\Models\Teams\Team;
use App\Models\User\User;

class DownloadTeamDataAction
{

    public function run(User $user, Team $team)
    {
        $path = now()->format('Y') .
            "/" . now()->format('m') .
            "/" . now()->format('d') .
            "/" . now()->getTimestamp() .
            '/_Team_OpenLitterMap.csv';  // 2020/10/25/unix/

        /* Dispatch job to create CSV file for export */
        (new CreateCSVExport(null, null, $team->id))
            ->queue($path, 's3', null, ['visibility' => 'public'])
            ->chain([
                // These jobs are executed when above is finished.
                new EmailUserExportCompleted($user->email, $path)
                // new ....job
            ]);
    }
}

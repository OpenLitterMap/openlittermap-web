<?php

namespace App\Actions\Teams;

use App\Exports\CreateCSVExport;
use App\Jobs\EmailUserExportCompleted;
use App\Models\Teams\Team;
use App\Models\Users\User;

class DownloadTeamDataAction
{

    public function run(User $user, Team $team, array $dateFilter = [], array $extraFilters = [], array $formats = ['split'], string $layout = 'wide'): void
    {
        $path = now()->format('Y') .
            "/" . now()->format('m') .
            "/" . now()->format('d') .
            "/" . now()->getTimestamp();

        if (!empty($dateFilter)) {
            $path .= '_from_' . $dateFilter['fromDate'] . '_to_' . $dateFilter['toDate'];
        }

        $path .= '/_Team_OpenLitterMap_' . CreateCSVExport::layoutSlug($layout) . '_' . now()->format('Y-m-d_His') . '_u' . $user->id . '_t' . $team->id . '.csv';

        /* Dispatch job to create CSV file for export */
        (new CreateCSVExport(null, null, $team->id, null, $dateFilter, $extraFilters, $formats, $layout))
            ->notifyOnFailure($user->email)
            ->queue($path, 's3', null, ['visibility' => 'public'])
            ->chain([
                // These jobs are executed when above is finished.
                new EmailUserExportCompleted($user->email, $path)
                // new ....job
            ]);
    }
}

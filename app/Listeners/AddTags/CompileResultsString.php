<?php

namespace App\Listeners\AddTags;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models\Photo;

class CompileResultsString
{
    /**
     * Instead of having to query the database to get the data for each photo
     * We save the metadata on the photos table to speed up page load
     * and avoid additional requests
     *
     * When a record exists, we apply the translation key => value,
     * for every item in each category.
     *
     * with this 1 line of code!
     *
     * @param  object  $event
     * @return void
     */
    public function handle ($event)
    {
        Photo::find($event->photo_id)->translate();
    }
}

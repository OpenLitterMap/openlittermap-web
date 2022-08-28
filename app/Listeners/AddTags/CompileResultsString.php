<?php

namespace App\Listeners\AddTags;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models\Photo;

class CompileResultsString implements ShouldQueue
{
    /**
     * Instead of having to query the database to get the related data for each photo
     * We save the metadata on the photos table as a string to speed up page load
     * and avoid additional requests
     *
     * When a record exists, we apply the translation key => value,
     * for every tag in each category.
     *
     * with this 1 line of code!
     *
     * Result:
     * photo.result_string = "smoking.butts 2, alcohol.beerBottles 1,"
     *
     * With this string, we can translate the tags into any language without querying the database.
     */
    public function handle ($event)
    {
        Photo::find($event->photo_id)->translate();
    }
}

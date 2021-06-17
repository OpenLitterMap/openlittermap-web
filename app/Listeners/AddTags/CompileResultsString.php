<?php

namespace App\Listeners\AddTags;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models\Photo;

class CompileResultsString implements ShouldQueue
{
    /**
     * Instead of having to query the database to get the category data for each photo
     * We save the metadata on the photos table as a string to speed up page load
     * and avoid additional requests
     *
     * When a record exists, we apply the translation key => value,
     * for every item in each category.
     *
     * with this 1 line of code!
     *
     * Result:
     * photo.result_string = "smoking.butts 2, alcohol.beerBottles 1,"
     *
     * This allows us to avoid eager loading relationships when loading big data on the global map
     * and we can translate the keys into any language
     */
    public function handle ($event)
    {
        Photo::find($event->photo_id)->translate();
    }
}

<?php

namespace App\Listeners\UpdateTags;

use App\Events\ResetTagsCountAdmin;
use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ResetCompileString
{
    /**
     * Handle the event.
     *
     * @param  ResetTagsCountAdmin  $event
     * @return void
     */
    public function handle (ResetTagsCountAdmin $event)
    {
        if ($photo = Photo::find($event->photo_id))
        {
            $photo->result_string = null;
            $photo->save();
        }
    }
}

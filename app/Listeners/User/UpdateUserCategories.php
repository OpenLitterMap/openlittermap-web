<?php

namespace App\Listeners\User;

use App\Events\TagsVerifiedByAdmin;
use App\Models\User\User;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateUserCategories
{
    /**
     * Handle the event.
     *
     * @param  TagsVerifiedByAdmin  $event
     * @return void
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        $user = User::find($event->user_id);

        // todo - merge this into dynamic function
        if ($event->total_alcohol)      $user->total_alcohol     += $event->total_alcohol;
        if ($event->total_coastal)      $user->total_coastal     += $event->total_coastal;
        if ($event->total_coffee)       $user->total_coffee      += $event->total_coffee;
        if ($event->total_dumping)      $user->total_dumping     += $event->total_dumping;
        if ($event->total_food)         $user->total_food        += $event->total_food;
        if ($event->total_industrial)   $user->total_industrial  += $event->total_industrial;
        if ($event->total_other)        $user->total_other       += $event->total_other;
        if ($event->total_sanitary)     $user->total_sanitary    += $event->total_sanitary;
        if ($event->total_softdrinks)   $user->total_softdrinks  += $event->total_softdrinks;
        if ($event->total_smoking)      $user->total_smoking     += $event->total_smoking;
        if ($event->total_brands)       $user->total_brands      += $event->total_brands;

        $user->save();
    }
}

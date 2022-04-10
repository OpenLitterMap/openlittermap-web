<?php

namespace App\Exports;

use App\Models\Category;
use App\Models\Photo;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CreateCSVExport implements FromQuery, WithMapping, WithHeadings
{
    use Exportable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $location_type, $location_id, $team_id, $user_id;

    /**
     * Init args
     */
    public function __construct ($location_type, $location_id, $team_id = null, $user_id = null)
    {
        $this->location_type = $location_type;
        $this->location_id = $location_id;
        $this->team_id = $team_id;
        $this->user_id = $user_id;
    }

    /**
     * Define column titles
     *
     * Todo - Add Country / State / City name
     * Todo - Allow the user to determine what data they want on the frontend
     * Todo - Import these from elsewhere
     * Todo - Separate brands by country
     * Todo - Insert translated string instead of hard-coded 1-language title
     * Todo - When downloading per team, show team member, if privacy true. (We need to create the privacy option first).
     */
    public function headings (): array
    {
        $result = [
            'id',
            'verification',
            'phone',
            'datetime',
            'lat',
            'lon',
//            'city',
//            'state',
//            'country',
            'picked up',
            'address',
            'total_litter',
        ];

        foreach (Category::with('tags')->get() as $category) {
            $result[] = strtoupper($category->name);
            $result = array_merge($result, $category->tags->pluck('name')->toArray());
        }

        return array_merge($result, ['custom_tag_1', 'custom_tag_2', 'custom_tag_3']);
    }

    /**
     * Map over query response
     * This will insert the each row under each heading
     * @param Photo $row
     */
    public function map ($row): array
    {
        $result = [
            $row->id,
            $row->verified,
            $row->model,
            $row->datetime,
            $row->lat,
            $row->lon,
//            $row->city_id, // todo -> name
//            $row->state_id, // todo -> name
//            $row->country_id, // todo -> name
            $row->remaining ? 'No' : 'Yes', // column name is "picked up"
            $row->display_name,
            $row->total_litter,
        ];

        /** @var Category $category */
        foreach (Category::with('tags')->get() as $category) {
            $result[] = null;

            foreach ($category->tags as $tag) {
                $result[] = $row->tags->where('pivot.tag_id', $tag->id)->first()->quantity ?? null;
            }
        }

        return array_merge($result, $row->customTags->take(3)->pluck('tag')->toArray());
    }

    /**
     * Create a query which we will loop over in the map function
     * no need to use ->get();
     */
    public function query ()
    {
        if ($this->user_id)
        {
            return Photo::with('tags')->where([
                'user_id' => $this->user_id
            ]);
        }

        else if ($this->team_id)
        {
            return Photo::with('tags')->where([
                'team_id' => $this->team_id,
                'verified' => 2
            ]);
        }

        else
        {
            if ($this->location_type === 'city')
            {
                return Photo::with('tags')->where([
                    'city_id' => $this->location_id,
                    'verified' => 2
                ]);
            }

            else if ($this->location_type === 'state')
            {
                return Photo::with('tags')->where([
                    'state_id' => $this->location_id,
                    'verified' => 2
                ]);
            }

            else
            {
                return Photo::with('tags')->where([
                    'country_id' => $this->location_id,
                    'verified' => 2
                ]);
            }
        }
    }
}

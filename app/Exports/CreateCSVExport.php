<?php

namespace App\Exports;

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
    /** @var array */
    private $dateFilter;

    public $timeout = 240;

    /**
     * Init args
     */
    public function __construct ($location_type, $location_id, $team_id = null, $user_id = null, array $dateFilter = [])
    {
        $this->location_type = $location_type;
        $this->location_id = $location_id;
        $this->team_id = $team_id;
        $this->user_id = $user_id;
        $this->dateFilter = $dateFilter;
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
            'date_taken',
            'date_uploaded',
            'lat',
            'lon',
//            'city',
//            'state',
//            'country',
            'picked up',
            'address',
            'total_litter',
        ];

        foreach (Photo::categories() as $category) {
            $result[] = strtoupper($category);

            // We make a temporary model to get the types, without persisting it
            $photo = new Photo;
            $model = $photo->$category()->make();
            $result = array_merge($result, $model->types());
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
            $row->created_at,
            $row->lat,
            $row->lon,
//            $row->city_id, // todo -> name
//            $row->state_id, // todo -> name
//            $row->country_id, // todo -> name
            $row->remaining ? 'No' : 'Yes', // column name is "picked up"
            $row->display_name,
            $row->total_litter,
        ];

        foreach (Photo::categories() as $category) {
            $result[] = null;

            // We make a temporary model to get the types, without persisting it
            $tags = $row->$category()->make()->types();

            foreach ($tags as $tag) {
                $result[] = $row->$category ? $row->$category->$tag : null;
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
        $query = Photo::with(Photo::categories());

        if (!empty($this->dateFilter))
        {
            $query->whereBetween(
                $this->dateFilter['column'],
                [$this->dateFilter['fromDate'], $this->dateFilter['toDate']]
            );
        }

        if ($this->user_id)
        {
            return $query->where([
                'user_id' => $this->user_id
            ]);
        }

        else if ($this->team_id)
        {
            return $query->where([
                'team_id' => $this->team_id,
                'verified' => 2
            ]);
        }

        else
        {
            if ($this->location_type === 'city')
            {
                return $query->where([
                    'city_id' => $this->location_id,
                    'verified' => 2
                ]);
            }

            else if ($this->location_type === 'state')
            {
                return $query->where([
                    'state_id' => $this->location_id,
                    'verified' => 2
                ]);
            }

            else
            {
                return $query->where([
                    'country_id' => $this->location_id,
                    'verified' => 2
                ]);
            }
        }
    }
}

<?php

namespace App\Exports;

use App\Models\Litter\Tags\Category;
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

    /**
     * Canonical category → objects mapping, loaded once from the DB.
     * Structure: [['id' => int, 'key' => string, 'objects' => [['id' => int, 'key' => string], ...]], ...]
     */
    private array $categoryObjects = [];

    public $timeout = 240;

    public function __construct($location_type, $location_id, $team_id = null, $user_id = null, array $dateFilter = [])
    {
        $this->location_type = $location_type;
        $this->location_id = $location_id;
        $this->team_id = $team_id;
        $this->user_id = $user_id;
        $this->dateFilter = $dateFilter;

        $this->categoryObjects = Category::with(['litterObjects' => fn ($q) => $q->orderBy('litter_objects.id')])
            ->orderBy('id')
            ->get()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'key' => $cat->key,
                'objects' => $cat->litterObjects->map(fn ($obj) => [
                    'id' => $obj->id,
                    'key' => $obj->key,
                ])->values()->toArray(),
            ])
            ->toArray();
    }

    /**
     * Define column titles.
     *
     * Layout: fixed columns, then [CATEGORY, object, object, ...] per category, then custom tags.
     */
    public function headings(): array
    {
        $result = [
            'id',
            'verification',
            'phone',
            'date_taken',
            'date_uploaded',
            'lat',
            'lon',
            'picked up',
            'address',
            'total_litter',
        ];

        foreach ($this->categoryObjects as $category) {
            $result[] = strtoupper($category['key']);

            foreach ($category['objects'] as $object) {
                $result[] = $object['key'];
            }
        }

        return array_merge($result, ['custom_tag_1', 'custom_tag_2', 'custom_tag_3']);
    }

    /**
     * Map a photo row to CSV columns using the summary JSON.
     *
     * @param Photo $row
     */
    public function map($row): array
    {
        $result = [
            $row->id,
            $row->verified,
            $row->model,
            $row->datetime,
            $row->created_at,
            $row->lat,
            $row->lon,
            $row->remaining ? 'No' : 'Yes',
            $row->display_name,
            $row->summary['totals']['total_objects'] ?? $row->total_litter,
        ];

        $tags = $row->summary['tags'] ?? [];

        foreach ($this->categoryObjects as $category) {
            $result[] = null; // category separator column
            $categoryTags = $tags[$category['id']] ?? [];

            foreach ($category['objects'] as $object) {
                $objectData = $categoryTags[$object['id']] ?? null;
                $result[] = $objectData['quantity'] ?? null;
            }
        }

        // Custom tags from v5 photo_tags (eager-loaded in query())
        $customTagNames = $row->photoTags
            ->filter(fn ($pt) => $pt->custom_tag_primary_id !== null)
            ->take(3)
            ->map(fn ($pt) => $pt->primaryCustomTag?->key)
            ->values()
            ->toArray();

        return array_merge($result, array_pad($customTagNames, 3, null));
    }

    /**
     * Create a query which we will loop over in the map function.
     */
    public function query()
    {
        $query = Photo::with(['photoTags' => function ($q) {
            $q->whereNotNull('custom_tag_primary_id')->with('primaryCustomTag');
        }]);

        if (!empty($this->dateFilter)) {
            $query->whereBetween(
                $this->dateFilter['column'],
                [$this->dateFilter['fromDate'], $this->dateFilter['toDate']]
            );
        }

        if ($this->user_id) {
            return $query->where(['user_id' => $this->user_id]);
        } elseif ($this->team_id) {
            return $query->where(['team_id' => $this->team_id, 'verified' => 2]);
        } elseif ($this->location_type === 'city') {
            return $query->where(['city_id' => $this->location_id, 'verified' => 2]);
        } elseif ($this->location_type === 'state') {
            return $query->where(['state_id' => $this->location_id, 'verified' => 2]);
        } else {
            return $query->where(['country_id' => $this->location_id, 'verified' => 2]);
        }
    }
}

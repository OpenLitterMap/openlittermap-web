<?php

namespace App\Exports;

use App\Enums\VerificationStatus;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObjectType;
use App\Models\Litter\Tags\Materials;
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

    /** @var array<int, array{id: int, key: string}> */
    private array $materials = [];

    /** @var array<int, array{id: int, key: string}> */
    private array $types = [];

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

        $this->materials = Materials::orderBy('id')->get()
            ->map(fn ($m) => ['id' => $m->id, 'key' => $m->key])
            ->toArray();

        $this->types = LitterObjectType::orderBy('id')->get()
            ->map(fn ($t) => ['id' => $t->id, 'key' => $t->key])
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
            'total_tags',
        ];

        foreach ($this->categoryObjects as $category) {
            $result[] = strtoupper($category['key']);

            foreach ($category['objects'] as $object) {
                $result[] = $object['key'];
            }
        }

        // Materials columns
        $result[] = 'MATERIALS';
        foreach ($this->materials as $material) {
            $result[] = $material['key'];
        }

        // Types columns
        $result[] = 'TYPES';
        foreach ($this->types as $type) {
            $result[] = $type['key'];
        }

        // Brands (single delimited column)
        $result[] = 'brands';

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
            $row->summary['totals']['litter'] ?? $row->total_tags,
        ];

        $tags = $row->summary['tags'] ?? [];
        $brandKeys = $row->summary['keys']['brands'] ?? [];

        // Single pass: iterate nested summary structure
        // Structure: { catId: { objId: { quantity, materials: {id: qty}, brands: {id: qty}, custom_tags } } }
        $tagLookup = [];
        $materialLookup = [];
        $brandParts = [];

        foreach ($tags as $catId => $objects) {
            foreach ($objects as $objId => $tagData) {
                $qty = $tagData['quantity'] ?? 0;
                $tagLookup[$catId][$objId] = ($tagLookup[$catId][$objId] ?? 0) + $qty;

                foreach ($tagData['materials'] ?? [] as $materialId => $materialQty) {
                    $materialLookup[$materialId] = ($materialLookup[$materialId] ?? 0) + $materialQty;
                }

                foreach ($tagData['brands'] ?? [] as $brandId => $brandQty) {
                    $brandName = $brandKeys[$brandId] ?? "brand_{$brandId}";
                    $brandParts[$brandName] = ($brandParts[$brandName] ?? 0) + $brandQty;
                }
            }
        }

        // Category/object columns
        foreach ($this->categoryObjects as $category) {
            $result[] = null; // category separator column

            foreach ($category['objects'] as $object) {
                $result[] = $tagLookup[$category['id']][$object['id']] ?? null;
            }
        }

        // Materials columns
        $result[] = null; // MATERIALS separator
        foreach ($this->materials as $material) {
            $result[] = $materialLookup[$material['id']] ?? null;
        }

        // Types: read from DB relationship (NOT summary — type_id doesn't exist in summary)
        $typeLookup = [];
        foreach ($row->photoTags as $pt) {
            if ($pt->litter_object_type_id) {
                $typeLookup[$pt->litter_object_type_id] =
                    ($typeLookup[$pt->litter_object_type_id] ?? 0) + $pt->quantity;
            }
        }

        $result[] = null; // TYPES separator
        foreach ($this->types as $type) {
            $result[] = $typeLookup[$type['id']] ?? null;
        }

        // Brands: single delimited column
        $result[] = !empty($brandParts)
            ? implode(';', array_map(fn ($name, $qty) => "{$name}:{$qty}", array_keys($brandParts), array_values($brandParts)))
            : null;

        // Custom tags from extra_tags (eager-loaded in query())
        $customTagNames = $row->photoTags
            ->flatMap(fn ($pt) => $pt->extraTags->where('tag_type', 'custom_tag'))
            ->take(3)
            ->map(fn ($extra) => $extra->extraTag?->key)
            ->values()
            ->toArray();

        return array_merge($result, array_pad($customTagNames, 3, null));
    }

    /**
     * Create a query which we will loop over in the map function.
     */
    public function query()
    {
        $query = Photo::with(['photoTags.extraTags.extraTag']);

        if (!empty($this->dateFilter)) {
            $query->whereBetween(
                $this->dateFilter['column'],
                [$this->dateFilter['fromDate'], $this->dateFilter['toDate']]
            );
        }

        if ($this->user_id) {
            return $query->where('user_id', $this->user_id);
        }

        // Team/location exports: only approved photos (ADMIN_APPROVED and above)
        $query->where('verified', '>=', VerificationStatus::ADMIN_APPROVED->value);

        if ($this->team_id) {
            return $query->where('team_id', $this->team_id);
        } elseif ($this->location_type === 'city') {
            return $query->where('city_id', $this->location_id);
        } elseif ($this->location_type === 'state') {
            return $query->where('state_id', $this->location_id);
        } else {
            return $query->where('country_id', $this->location_id);
        }
    }
}

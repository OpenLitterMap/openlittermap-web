<?php

namespace App\Exports;

use App\Enums\VerificationStatus;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObjectType;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Photo;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\ExportFailed;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CreateCSVExport implements FromQuery, WithMapping, WithHeadings
{
    use Exportable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $location_type, $location_id, $team_id, $user_id;
    /** @var string|null recipient for failure notification */
    public ?string $notifyEmail = null;
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

    private bool $hasBrands = false;
    private bool $hasCustomTags = false;

    public $timeout = 240;

    public function __construct($location_type, $location_id, $team_id = null, $user_id = null, array $dateFilter = [])
    {
        $this->location_type = $location_type;
        $this->location_id = $location_id;
        $this->team_id = $team_id;
        $this->user_id = $user_id;
        $this->dateFilter = $dateFilter;

        // Pre-scan: find which columns actually have data for this export scope.
        // Use subqueries (not pluck) so MySQL optimizes internally for large exports.
        $photoIdQuery = $this->scopeQuery(Photo::query())->select('id');
        $tagIdQuery = PhotoTag::whereIn('photo_id', $photoIdQuery)->select('id');

        $activeObjectIds = PhotoTag::whereIn('photo_id', $photoIdQuery)
            ->whereNotNull('category_id')
            ->whereNotNull('litter_object_id')
            ->select('category_id', 'litter_object_id')
            ->distinct()
            ->get();

        $activeCatIds = $activeObjectIds->pluck('category_id')->unique()->all();
        $activeObjMap = $activeObjectIds->groupBy('category_id')
            ->map(fn ($rows) => $rows->pluck('litter_object_id')->all())
            ->all();

        // Single query for all extra tag types
        $extraTagTypes = PhotoTagExtraTags::whereIn('photo_tag_id', $tagIdQuery)
            ->select('tag_type', 'tag_type_id')
            ->distinct()
            ->get();

        $activeMaterialIds = $extraTagTypes->where('tag_type', 'material')->pluck('tag_type_id')->all();
        $this->hasBrands = $extraTagTypes->where('tag_type', 'brand')->isNotEmpty();
        $this->hasCustomTags = $extraTagTypes->where('tag_type', 'custom_tag')->isNotEmpty();

        $activeTypeIds = PhotoTag::whereIn('photo_id', $photoIdQuery)
            ->whereNotNull('litter_object_type_id')
            ->distinct()
            ->pluck('litter_object_type_id')
            ->all();

        // Load and filter category/object columns to only those with data
        $this->categoryObjects = Category::with(['litterObjects' => fn ($q) => $q->orderBy('litter_objects.id')])
            ->whereIn('id', $activeCatIds)
            ->orderBy('id')
            ->get()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'key' => $cat->key,
                'objects' => $cat->litterObjects
                    ->filter(fn ($obj) => in_array($obj->id, $activeObjMap[$cat->id] ?? []))
                    ->map(fn ($obj) => ['id' => $obj->id, 'key' => $obj->key])
                    ->values()
                    ->toArray(),
            ])
            ->filter(fn ($cat) => !empty($cat['objects']))
            ->values()
            ->toArray();

        // Filter materials and types to only those with data
        $this->materials = !empty($activeMaterialIds)
            ? Materials::whereIn('id', $activeMaterialIds)->orderBy('id')->get()
                ->map(fn ($m) => ['id' => $m->id, 'key' => $m->key])
                ->toArray()
            : [];

        $this->types = !empty($activeTypeIds)
            ? LitterObjectType::whereIn('id', $activeTypeIds)->orderBy('id')->get()
                ->map(fn ($t) => ['id' => $t->id, 'key' => $t->key])
                ->toArray()
            : [];
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

        if (!empty($this->materials)) {
            $result[] = 'MATERIALS';
            foreach ($this->materials as $material) {
                $result[] = $material['key'];
            }
        }

        if (!empty($this->types)) {
            $result[] = 'TYPES';
            foreach ($this->types as $type) {
                $result[] = $type['key'];
            }
        }

        if ($this->hasBrands) {
            $result[] = 'brands';
        }

        if ($this->hasCustomTags) {
            $result = array_merge($result, ['custom_tag_1', 'custom_tag_2', 'custom_tag_3']);
        }

        return $result;
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
            $row->verified?->value ?? 0,
            $row->model,
            $row->datetime,
            $row->created_at,
            $row->lat,
            $row->lon,
            $row->picked_up ? 'Yes' : 'No',
            $row->display_name,
            $row->summary['totals']['litter'] ?? $row->total_tags,
        ];

        $tags = $row->summary['tags'] ?? [];
        $brandKeys = $row->summary['keys']['brands'] ?? [];

        // Single pass: iterate flat tags array
        // Structure: [ { clo_id, category_id, object_id, type_id, quantity, materials: [id, ...], brands: {id: qty}, custom_tags: [id, ...] } ]
        $tagLookup = [];
        $materialLookup = [];
        $typeLookup = [];
        $brandParts = [];

        foreach ($tags as $tag) {
            $catId = $tag['category_id'] ?? 0;
            $objId = $tag['object_id'] ?? 0;
            $qty = $tag['quantity'] ?? 0;

            $tagLookup[$catId][$objId] = ($tagLookup[$catId][$objId] ?? 0) + $qty;

            // Materials: array of IDs — each gets the parent tag's quantity
            foreach ($tag['materials'] ?? [] as $materialId) {
                $materialLookup[$materialId] = ($materialLookup[$materialId] ?? 0) + $qty;
            }

            // Types: type_id is in the summary
            $typeId = $tag['type_id'] ?? null;
            if ($typeId) {
                $typeLookup[$typeId] = ($typeLookup[$typeId] ?? 0) + $qty;
            }

            // Brands: {id: qty} objects with independent quantities
            foreach ($tag['brands'] ?? [] as $brandId => $brandQty) {
                $brandName = $brandKeys[$brandId] ?? "brand_{$brandId}";
                $brandParts[$brandName] = ($brandParts[$brandName] ?? 0) + $brandQty;
            }
        }

        // Category/object columns
        foreach ($this->categoryObjects as $category) {
            $result[] = null; // category separator column

            foreach ($category['objects'] as $object) {
                $result[] = $tagLookup[$category['id']][$object['id']] ?? null;
            }
        }

        // Materials columns (only if any exist in export scope)
        if (!empty($this->materials)) {
            $result[] = null; // MATERIALS separator
            foreach ($this->materials as $material) {
                $result[] = $materialLookup[$material['id']] ?? null;
            }
        }

        // Types columns (only if any exist in export scope)
        if (!empty($this->types)) {
            $result[] = null; // TYPES separator
            foreach ($this->types as $type) {
                $result[] = $typeLookup[$type['id']] ?? null;
            }
        }

        // Brands: single delimited column (only if any exist in export scope)
        if ($this->hasBrands) {
            $result[] = !empty($brandParts)
                ? implode(';', array_map(fn ($name, $qty) => "{$name}:{$qty}", array_keys($brandParts), array_values($brandParts)))
                : null;
        }

        // Custom tags (only if any exist in export scope)
        if ($this->hasCustomTags) {
            $customTagNames = $row->photoTags
                ->flatMap(fn ($pt) => $pt->extraTags->where('tag_type', 'custom_tag'))
                ->take(3)
                ->map(fn ($extra) => $extra->extraTag?->key)
                ->values()
                ->toArray();

            $result = array_merge($result, array_pad($customTagNames, 3, null));
        }

        return $result;
    }

    /**
     * Create a query which we will loop over in the map function.
     */
    public function query()
    {
        return $this->scopeQuery(
            Photo::with(['photoTags.extraTags.extraTag'])
        );
    }

    /**
     * Fluent setter for the recipient email — used to notify the user if the export fails.
     */
    public function notifyOnFailure(?string $email): self
    {
        $this->notifyEmail = $email;

        return $this;
    }

    /**
     * Called by Maatwebsite Excel when any queued sheet job fails.
     * We email the user so they aren't left waiting forever for a download that will never arrive.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('CreateCSVExport failed', [
            'user_id' => $this->user_id,
            'team_id' => $this->team_id,
            'location_type' => $this->location_type,
            'location_id' => $this->location_id,
            'notifyEmail' => $this->notifyEmail,
            'error' => $e->getMessage(),
        ]);

        if ($this->notifyEmail) {
            try {
                Mail::to($this->notifyEmail)->send(new ExportFailed());
            } catch (\Throwable $mailError) {
                Log::error('Failed to send ExportFailed mail', [
                    'to' => $this->notifyEmail,
                    'error' => $mailError->getMessage(),
                ]);
            }
        }
    }

    /**
     * Apply the export scope (user/team/location + date filter + verification) to a query.
     */
    private function scopeQuery($query)
    {
        if (!empty($this->dateFilter)) {
            $allowedColumns = ['created_at', 'datetime', 'updated_at'];
            $column = in_array($this->dateFilter['column'], $allowedColumns, true)
                ? $this->dateFilter['column']
                : 'datetime';

            $query->whereBetween($column, [
                $this->dateFilter['fromDate'],
                $this->dateFilter['toDate'],
            ]);
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

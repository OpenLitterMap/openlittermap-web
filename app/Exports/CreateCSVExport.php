<?php

namespace App\Exports;

use App\Enums\VerificationStatus;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObjectType;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Photo;
use Illuminate\Support\Collection;

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

    /** @var array Extra filters for team exports (tag, custom_tag, picked_up, member_id, status) */
    private array $extraFilters;

    /** @var bool Emit the v5 split block (per-category object columns + TYPES block). */
    private bool $emitSplit = true;

    /** @var bool Emit the v4-style joined block (one column per {type}_{object}, per category). */
    private bool $emitJoined = false;

    /**
     * Per-category joined column descriptors.
     * [['category_id'=>int,'category_key'=>string,'columns'=>[['key'=>'spirits_bottle','object_id'=>int,'type_id'=>int|null]]]].
     */
    private array $categoryJoinedColumns = [];

    public $timeout = 240;

    /**
     * @param array<string> $formats Subset of ['split','joined']. Empty/invalid → ['split'].
     */
    public function __construct($location_type, $location_id, $team_id = null, $user_id = null, array $dateFilter = [], array $extraFilters = [], array $formats = ['split'])
    {
        $this->location_type = $location_type;
        $this->location_id = $location_id;
        $this->team_id = $team_id;
        $this->user_id = $user_id;
        $this->dateFilter = $dateFilter;
        $this->extraFilters = $extraFilters;

        $normalized = self::normalizeFormats($formats);
        $this->emitSplit = in_array('split', $normalized, true);
        $this->emitJoined = in_array('joined', $normalized, true);

        // Pre-scan: find which columns actually have data for this export scope.
        // Use subqueries (not pluck) so MySQL optimizes internally for large exports.
        $photoIdQuery = $this->scopeQuery(Photo::query())->select('id');
        $tagIdQuery = PhotoTag::whereIn('photo_id', $photoIdQuery)->select('id');

        // Joined mode needs (cat, obj, type) triples; split-only needs (cat, obj) + types separately.
        // Combining into a single triple scan when joined avoids a second filtered photo_tags scan.
        if ($this->emitJoined) {
            $activeTriples = PhotoTag::whereIn('photo_id', $photoIdQuery)
                ->whereNotNull('category_id')
                ->whereNotNull('litter_object_id')
                ->select('category_id', 'litter_object_id', 'litter_object_type_id')
                ->distinct()
                ->get();

            $activeObjectIds = $activeTriples;
            $activeTypeIds = $activeTriples
                ->pluck('litter_object_type_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
        } else {
            $activeObjectIds = PhotoTag::whereIn('photo_id', $photoIdQuery)
                ->whereNotNull('category_id')
                ->whereNotNull('litter_object_id')
                ->select('category_id', 'litter_object_id')
                ->distinct()
                ->get();

            $activeTypeIds = PhotoTag::whereIn('photo_id', $photoIdQuery)
                ->whereNotNull('litter_object_type_id')
                ->distinct()
                ->pluck('litter_object_type_id')
                ->all();

            $activeTriples = collect();
        }

        $activeCatIds = $activeObjectIds->pluck('category_id')->unique()->all();
        $activeObjMap = $activeObjectIds->groupBy('category_id')
            ->map(fn ($rows) => $rows->pluck('litter_object_id')->unique()->all())
            ->all();

        // Single query for all extra tag types
        $extraTagTypes = PhotoTagExtraTags::whereIn('photo_tag_id', $tagIdQuery)
            ->select('tag_type', 'tag_type_id')
            ->distinct()
            ->get();

        $activeMaterialIds = $extraTagTypes->where('tag_type', 'material')->pluck('tag_type_id')->all();
        $this->hasBrands = $extraTagTypes->where('tag_type', 'brand')->isNotEmpty();
        $this->hasCustomTags = $extraTagTypes->where('tag_type', 'custom_tag')->isNotEmpty();

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

        if ($this->emitJoined) {
            $this->categoryJoinedColumns = $this->buildJoinedColumns($activeTriples);
        }
    }

    /**
     * Normalize a raw format list (array or comma-string-friendly) into a deduped subset of ['split','joined'].
     * Falls back to ['split'] when the result would be empty.
     *
     * @param array<int, string> $formats
     * @return array<int, string>
     */
    public static function normalizeFormats(array $formats): array
    {
        $allowed = ['split', 'joined'];
        $clean = [];

        foreach ($formats as $f) {
            $f = strtolower(trim((string) $f));
            if (in_array($f, $allowed, true) && ! in_array($f, $clean, true)) {
                $clean[] = $f;
            }
        }

        return empty($clean) ? ['split'] : $clean;
    }

    /**
     * Parse the `format` request param (comma-separated string) into a normalized format list.
     * Use this from controllers so parsing/validation stays in one place.
     */
    public static function parseFormats(?string $raw): array
    {
        return self::normalizeFormats(array_filter(explode(',', (string) $raw)));
    }

    /**
     * Build the per-category column list for the joined block from the constructor's triple scan.
     * Reuses already-loaded categoryObjects/types — no extra DB queries.
     *
     * @return array<int, array{category_id:int, category_key:string, columns:array<int, array{key:string, object_id:int, type_id:int|null}>}>
     */
    private function buildJoinedColumns(Collection $triples): array
    {
        if ($triples->isEmpty()) {
            return [];
        }

        $catKeys = array_column($this->categoryObjects, 'key', 'id');
        $typeKeys = array_column($this->types, 'key', 'id');

        $objKeys = [];
        foreach ($this->categoryObjects as $cat) {
            foreach ($cat['objects'] as $obj) {
                $objKeys[$obj['id']] = $obj['key'];
            }
        }

        $byCat = [];
        foreach ($triples as $row) {
            $catId = (int) $row->category_id;
            $objId = (int) $row->litter_object_id;
            $typeId = $row->litter_object_type_id !== null ? (int) $row->litter_object_type_id : null;

            $objKey = $objKeys[$objId] ?? null;
            if ($objKey === null) {
                continue;
            }

            $key = $typeId !== null && isset($typeKeys[$typeId])
                ? $typeKeys[$typeId] . '_' . $objKey
                : $objKey;

            $byCat[$catId][] = [
                'key' => $key,
                'object_id' => $objId,
                'type_id' => $typeId,
            ];
        }

        $result = [];
        foreach ($catKeys as $catId => $catKey) {
            if (empty($byCat[$catId])) {
                continue;
            }

            $columns = collect($byCat[$catId])
                ->sortBy(fn ($c) => sprintf('%010d-%010d', $c['object_id'], $c['type_id'] ?? 0))
                ->values()
                ->all();

            $result[] = [
                'category_id' => $catId,
                'category_key' => $catKey,
                'columns' => $columns,
            ];
        }

        return $result;
    }

    /**
     * Define column titles. Order: fixed → split block (when emitSplit) →
     * MATERIALS → brands → custom tags → joined block (when emitJoined).
     *
     * Joined block appends after the shared MATERIALS/brands/custom block so
     * users requesting `format=split,joined` see the v5 layout first, then the
     * v4-style joined columns. Joined-only mode suppresses the per-category
     * split columns AND the TYPES block (their data is in the joined keys).
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

        if ($this->emitSplit) {
            foreach ($this->categoryObjects as $category) {
                $result[] = strtoupper($category['key']);

                foreach ($category['objects'] as $object) {
                    $result[] = $object['key'];
                }
            }
        }

        if (!empty($this->materials)) {
            $result[] = 'MATERIALS';
            foreach ($this->materials as $material) {
                $result[] = $material['key'];
            }
        }

        if ($this->emitSplit && !empty($this->types)) {
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

        if ($this->emitJoined) {
            foreach ($this->categoryJoinedColumns as $category) {
                $result[] = strtoupper($category['category_key']);
                foreach ($category['columns'] as $col) {
                    $result[] = $col['key'];
                }
            }
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
            $row->total_tags,
        ];

        $tags = $row->summary['tags'] ?? [];
        $brandKeys = $row->summary['keys']['brands'] ?? [];

        // Single pass: iterate flat tags array
        // Structure: [ { clo_id, category_id, object_id, type_id, quantity, materials: [id, ...], brands: {id: qty}, custom_tags: [id, ...] } ]
        $tagLookup = [];
        $materialLookup = [];
        $typeLookup = [];
        $brandParts = [];
        $joinedLookup = [];

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

            if ($this->emitJoined && $catId && $objId) {
                $joinedTypeKey = $typeId ?? 0;
                $joinedLookup[$catId][$objId][$joinedTypeKey] =
                    ($joinedLookup[$catId][$objId][$joinedTypeKey] ?? 0) + $qty;
            }

            // Brands: {id: qty} objects with independent quantities
            foreach ($tag['brands'] ?? [] as $brandId => $brandQty) {
                $brandName = $brandKeys[$brandId] ?? "brand_{$brandId}";
                $brandParts[$brandName] = ($brandParts[$brandName] ?? 0) + $brandQty;
            }
        }

        // Split block: category/object columns
        if ($this->emitSplit) {
            foreach ($this->categoryObjects as $category) {
                $result[] = null; // category separator column

                foreach ($category['objects'] as $object) {
                    $result[] = $tagLookup[$category['id']][$object['id']] ?? null;
                }
            }
        }

        // Materials columns (only if any exist in export scope)
        if (!empty($this->materials)) {
            $result[] = null; // MATERIALS separator
            foreach ($this->materials as $material) {
                $result[] = $materialLookup[$material['id']] ?? null;
            }
        }

        // Types columns (split block only — joined columns subsume the type dimension)
        if ($this->emitSplit && !empty($this->types)) {
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

        // Custom tags (only if any exist in export scope).
        // Walks the relation rather than reading summary['tags'][i]['custom_tags'] because
        // some legacy rows have stale per-tag arrays (e.g. extras-only photos where summary
        // pre-dates a custom_tag write). The eager-loaded photoTags.extraTags.extraTag
        // chain in query() keeps this O(1) per row.
        if ($this->hasCustomTags) {
            $customTagNames = $row->photoTags
                ->flatMap(fn ($pt) => $pt->extraTags->where('tag_type', 'custom_tag'))
                ->take(3)
                ->map(fn ($extra) => $extra->extraTag?->key)
                ->values()
                ->toArray();

            $result = array_merge($result, array_pad($customTagNames, 3, null));
        }

        // Joined block: per-category {type}_{object} columns (or bare {object} when no type).
        if ($this->emitJoined) {
            foreach ($this->categoryJoinedColumns as $category) {
                $result[] = null; // category separator column
                $catId = $category['category_id'];
                foreach ($category['columns'] as $col) {
                    $typeKey = $col['type_id'] ?? 0;
                    $result[] = $joinedLookup[$catId][$col['object_id']][$typeKey] ?? null;
                }
            }
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
            $query->where('team_id', $this->team_id);
        } elseif ($this->location_type === 'city') {
            $query->where('city_id', $this->location_id);
        } elseif ($this->location_type === 'state') {
            $query->where('state_id', $this->location_id);
        } else {
            $query->where('country_id', $this->location_id);
        }

        $this->applyExtraFilters($query);

        return $query;
    }

    /**
     * Apply additional filters for team exports (tag, custom_tag, picked_up, member_id, status).
     */
    private function applyExtraFilters($query): void
    {
        if (empty($this->extraFilters)) {
            return;
        }

        if (!empty($this->extraFilters['tag'])) {
            $tag = $this->extraFilters['tag'];
            $query->whereHas('photoTags.object', function ($q) use ($tag) {
                $q->where('key', 'like', "%{$tag}%");
            });
        }

        if (!empty($this->extraFilters['custom_tag'])) {
            $customTag = $this->extraFilters['custom_tag'];
            $query->whereHas('photoTags.extraTags', function ($q) use ($customTag) {
                $q->where('tag_type', 'custom_tag')
                    ->whereHas('extraTag', function ($q2) use ($customTag) {
                        $q2->where('key', 'like', "%{$customTag}%");
                    });
            });
        }

        if (isset($this->extraFilters['picked_up'])) {
            $pickedUp = $this->extraFilters['picked_up'];
            if ($pickedUp === 'true') {
                $query->whereHas('photoTags', fn ($q) => $q->where('picked_up', true));
            } elseif ($pickedUp === 'false') {
                $query->whereHas('photoTags', fn ($q) => $q->where('picked_up', false));
            }
        }

        if (!empty($this->extraFilters['member_id'])) {
            $query->where('user_id', (int) $this->extraFilters['member_id']);
        }

        if (!empty($this->extraFilters['status']) && $this->team_id) {
            $status = $this->extraFilters['status'];
            if ($status === 'pending') {
                $query->where('is_public', false)->whereNull('team_approved_at');
            } elseif ($status === 'approved') {
                $query->whereNotNull('team_approved_at');
            }
        }
    }
}

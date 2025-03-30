<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\CustomTag;
use App\Models\Litter\Tags\CustomTagNew;
use Illuminate\Console\Command;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\LitterObject;

class PreMigrationScript extends Command
{
    protected $signature = 'olm:update:pre-migration-v5';
    protected $description = 'Process tags from custom_tags with simple classification and create new records if not already found.';

    // Prevent duplicate log messages
    protected $loggedTags = [];

    // Pre-loaded arrays of known keys (all lowercased)
    protected $brandKeys = [];
    protected $categoryKeys = [];
    protected $objectKeys = [];
    protected $materialKeys = [];
    protected $customTagKeys = [];

    // Custom category mappings (column A => column B)
    protected $categoryMaps = [
        'alcohol can'      => 'alcohol',
        'bikepart'         => 'bikeparts',
        'narcotics'        => 'drugs',
        'motorzeis'        => 'other',
        'bosmaaier'        => 'other',
        'firework'         => 'fireworks',
        'bycicle'          => 'cycling',
        'fastfoos'         => 'fastfood',
        'firelwork'        => 'fireworks',
        'buildingmaterials'=> 'industrial',
        'medicine'         => 'medical',
    ];

    protected $subcategories = [
        'icecream'    => 'food',
        'dairy'       => 'food',
        'fruit'       => 'food',
        'fastfood'    => 'food',
        'energydrink' => 'softdrinks',
        'bikeparts'   => 'cycling',
        'bicycle'     => 'cycling',
    ];

    protected $materialMaps = [
        'platic' => 'plastic',
        'plasric' => 'plastic',
        'cardboard packaging' => 'cardboard',
    ];

    public function handle()
    {
        $this->info("🚀 Starting to process custom_tags...");

        // Pre-load known keys from lookup tables (all lowercased)
        $this->brandKeys    = array_map('strtolower', BrandList::pluck('key')->all());
        $this->categoryKeys = array_map('strtolower', Category::pluck('key')->all());
        $this->objectKeys   = array_map('strtolower', LitterObject::pluck('key')->all());
        $this->materialKeys = array_map('strtolower', Materials::pluck('key')->all());
        $this->customTagKeys = array_map('strtolower', CustomTagNew::pluck('key')->all());

        // Process custom tags in chunks
        CustomTag::chunk(100, function ($customTags) {
            foreach ($customTags as $customTag) {
                $this->processTag($customTag->tag);
            }
        });

        sort($this->objectKeys, SORT_STRING);

        foreach ($this->objectKeys as $key) {
            $this->info($key);
        }

        $this->info("✅ Tag processing completed.");
    }

    /**
     * Process a raw tag.
     *
     * If the tag contains a colon and the prefix matches a generic type,
     * use that type hint. Otherwise, split and process each segment.
     */
    protected function processTag(string $rawTag)
    {
        if (strpos($rawTag, ':') !== false) {
            list($prefix, $value) = array_map('trim', explode(':', $rawTag, 2));
            $genericTypes = ['brand', 'category', 'object', 'material'];
            if (in_array(strtolower($prefix), $genericTypes)) {
                $this->classifyAndLogTag($value, ucfirst($prefix));
                return;
            }
            foreach (explode(':', $rawTag) as $segment) {
                $this->processSegment(trim($segment));
            }
            return;
        }
        $this->processSegment(trim($rawTag));
    }

    /**
     * Process an individual tag segment if it is not generic or ignored.
     */
    protected function processSegment(string $segment)
    {
        $lower   = strtolower($segment);
        $generic = ['brand', 'brands', 'bn', 'category', 'cat', 'object', 'objects', 'material', 'materials'];
        $ignore  = array_map('strtolower', CustomTag::notIncludeTags());

        if (in_array($lower, $generic) || in_array($lower, $ignore)) {
            return;
        }

        $this->classifyAndLogTag($segment);
    }

    /**
     * Classifies a tag and logs it.
     *
     * If a type hint is provided, it is used directly (with mapping for Categories).
     * Otherwise, the tag is classified using determineTagType().
     */
    protected function classifyAndLogTag(string $tagString, string $typeHint = null)
    {
        $cleanTag = trim($tagString);

        // Remove any trailing "=number" suffix (quantity handling removed for simplicity)
        if (preg_match('/^(.*)=(\d+)$/', $cleanTag, $matches)) {
            $cleanTag = trim($matches[1]);
        }

        if ($typeHint) {
            $lower = strtolower($cleanTag);
            if (strtolower($typeHint) === 'category') {
                if (array_key_exists($lower, $this->categoryMaps)) {
                    $cleanTag = $this->categoryMaps[$lower];
                }
            } elseif (strtolower($typeHint) === 'material') {
                if (array_key_exists($lower, $this->materialMaps)) {
                    $cleanTag = $this->materialMaps[$lower];
                }
            }
            $type = ucfirst($typeHint);
        } else {
            $result = $this->determineTagType($cleanTag);
            $type   = $result['type'];
            if (isset($result['tag'])) {
                $cleanTag = $result['tag'];
            }
        }

        $this->addTag($cleanTag, $type);
    }

    /**
     * Classifies a tag based on its value.
     *
     * Returns an array with the determined type and, if applicable, a canonical tag.
     */
    protected function determineTagType(string $tag): array
    {
        $clean = trim($tag);
        $lower = strtolower($clean);

        if (strlen($clean) === 1) {
            return ['type' => 'Undefined'];
        }

        if (array_key_exists($lower, $this->categoryMaps)) {
            return ['type' => 'Category', 'tag' => $this->categoryMaps[$lower]];
        }
        if (in_array($lower, $this->brandKeys)) {
            return ['type' => 'Brand', 'tag' => $clean];
        }
        if (in_array($lower, $this->categoryKeys)) {
            return ['type' => 'Category', 'tag' => $clean];
        }
        if (in_array($lower, $this->objectKeys)) {
            return ['type' => 'Object', 'tag' => $clean];
        }
        if (in_array($lower, $this->materialKeys)) {
            return ['type' => 'Material', 'tag' => $clean];
        }

        return ['type' => 'CustomTagNew', 'tag' => $clean];
    }

    /**
     * Logs the tag and creates a new record if not already processed.
     */
    protected function addTag(string $cleanTag, string $type)
    {
        $lowerKey = strtolower($cleanTag);
        $loggedKey = $type . '|' . $lowerKey;
        if (in_array($loggedKey, $this->loggedTags)) {
            return;
        }

        switch ($type) {
            case 'Category':
                $parentId = null;
                if (array_key_exists($lowerKey, $this->subcategories)) {
                    $parentKey = strtolower($this->subcategories[$lowerKey]);
                    $parentCategory = Category::firstOrCreate(
                        ['key' => $parentKey],
                        ['crowdsourced' => true]
                    );
                    $parentId = $parentCategory->id;
                }
                if (!in_array($lowerKey, $this->categoryKeys)) {
                    Category::firstOrCreate(
                        ['key' => $lowerKey, 'parent_id' => $parentId],
                        ['crowdsourced' => true]
                    );
                    $this->categoryKeys[] = $lowerKey;
                    $message = "Created new Category: $lowerKey";
                    if ($parentId) {
                        $message .= " (Parent: $parentKey)";
                    }
                    $this->info($message);
                }
                break;
            case 'Brand':
                if (!in_array($lowerKey, $this->brandKeys)) {
                    BrandList::firstOrCreate(['key' => $lowerKey], ['crowdsourced' => 1]);
                    $this->brandKeys[] = $lowerKey;
                    $this->info("Created new Brand: $lowerKey");
                }
                break;
            case 'Object':
                if (!in_array($lowerKey, $this->objectKeys)) {
                    LitterObject::firstOrCreate(['key' => $lowerKey], ['crowdsourced' => 1]);
                    $this->objectKeys[] = $lowerKey;
                    $this->info("Created new Object: $lowerKey");
                }
                break;
            case 'Material':
                if (!in_array($lowerKey, $this->materialKeys)) {
                    Materials::create(['key' => $lowerKey], ['crowdsourced' => 1]);
                    $this->materialKeys[] = $lowerKey;
                    $this->info("Created new Material: $lowerKey");
                }
                break;
            case 'CustomTagNew':
                if (!in_array($lowerKey, $this->customTagKeys)) {
                    CustomTagNew::firstOrCreate(['key' => $lowerKey], ['crowdsourced' => 1]);
                    $this->customTagKeys[] = $lowerKey;
                    $this->info("Created new custom tag: $lowerKey");
                }
                break;
            default:
                break;
        }

        $this->loggedTags[] = $loggedKey;
    }
}

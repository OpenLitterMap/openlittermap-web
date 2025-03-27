<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\CustomTag;
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

    public function handle()
    {
        $this->info("🚀 Starting to process custom_tags (simple version)...");

        // Pre-load known keys from lookup tables, all lowercased
        $this->brandKeys    = array_map('strtolower', BrandList::pluck('key')->all());
        $this->categoryKeys = array_map('strtolower', Category::pluck('key')->all());
        $this->objectKeys   = array_map('strtolower', LitterObject::pluck('key')->all());
        $this->materialKeys = array_map('strtolower', Materials::pluck('key')->all());

        // Process custom tags in chunks
        CustomTag::chunk(100, function ($customTags) {
            foreach ($customTags as $customTag) {
                $this->processTag($customTag->tag);
            }
        });

        $this->info("✅ Tag processing completed.");
    }

    /**
     * Processes a raw tag.
     *
     * If a colon is present and the prefix matches one of the known types
     * (brand, category, object, material), then use that type hint.
     * Otherwise, split the tag by colon and process each segment individually.
     */
    protected function processTag(string $rawTag)
    {
        if (strpos($rawTag, ':') !== false) {
            // Only split into two parts so we keep the type hint with the value
            list($prefix, $value) = explode(':', $rawTag, 2);
            $prefix = trim($prefix);
            $value  = trim($value);

            $typeHint = strtolower($prefix);
            $genericTypes = ['brand', 'category', 'object', 'material'];
            if (in_array($typeHint, $genericTypes)) {
                // Use the prefix as the type
                $this->classifyAndLogWithType($value, ucfirst($typeHint));
                return;
            } else {
                // Not a recognized prefix; process each segment individually.
                foreach (explode(':', $rawTag) as $segment) {
                    $this->processSegment(trim($segment));
                }
                return;
            }
        }
        $this->processSegment(trim($rawTag));
    }

    /**
     * Skips generic/ignore-list words; classifies otherwise.
     */
    protected function processSegment(string $segment)
    {
        $lower   = strtolower($segment);
        $generic = ['brand', 'brands', 'bn', 'category', 'cat', 'object', 'objects', 'material', 'materials'];
        $ignore  = array_map('strtolower', CustomTag::notIncludeTags());

        // Skip if it’s a generic or ignored term
        if (in_array($lower, $generic) || in_array($lower, $ignore)) {
            return;
        }

        $this->classifyAndLog($segment);
    }

    /**
     * Removes any trailing "=number" style quantity, then classifies and logs.
     */
    protected function classifyAndLog(string $tagString)
    {
        $quantity = null;
        $cleanTag = trim($tagString);

        // Detect "=number" suffix
        if (preg_match('/^(.*)=(\d+)$/', $cleanTag, $matches)) {
            $cleanTag = trim($matches[1]);
            $quantity = (int) $matches[2];
        }

        // Determine the tag type based on lookup arrays
        $result = $this->determineTagType($cleanTag);
        $type   = $result['type'];

        // For display purposes, re-add the quantity suffix if applicable.
        $display = $cleanTag . ($quantity !== null ? "={$quantity}" : '');

        $this->logTag($cleanTag, $display, $type);
    }

    /**
     * Similar to classifyAndLog() but uses a provided type hint.
     */
    protected function classifyAndLogWithType(string $tagString, string $type)
    {
        $quantity = null;
        $cleanTag = trim($tagString);

        if (preg_match('/^(.*)=(\d+)$/', $cleanTag, $matches)) {
            $cleanTag = trim($matches[1]);
            $quantity = (int) $matches[2];
        }

        $display = $cleanTag . ($quantity !== null ? "={$quantity}" : '');
        $this->logTag($cleanTag, $display, $type);
    }

    /**
     * A simple classification approach for tags without explicit type hint:
     *   1) Single character => Undefined
     *   2) Checks against lookup arrays (brand, category, object, material) in order.
     *   3) Otherwise, fallback => "CustomTagNew"
     */
    protected function determineTagType(string $tag)
    {
        $clean = trim($tag);
        $lower = strtolower($clean);

        if (strlen($clean) === 1) {
            return ['type' => 'Undefined'];
        }

        if (in_array($lower, $this->brandKeys)) {
            return ['type' => 'Brand'];
        }
        if (in_array($lower, $this->categoryKeys)) {
            return ['type' => 'Category'];
        }
        if (in_array($lower, $this->objectKeys)) {
            return ['type' => 'Object'];
        }
        if (in_array($lower, $this->materialKeys)) {
            return ['type' => 'Material'];
        }

        return ['type' => 'CustomTagNew'];
    }

    /**
     * Logs the tag and creates a new record if it wasn’t already processed.
     */
    protected function logTag(string $cleanTag, string $display, string $type)
    {
        $lowerKey = strtolower($cleanTag);
        $loggedKey = $type . '|' . $lowerKey;
        if (in_array($loggedKey, $this->loggedTags)) {
            return;
        }

        switch ($type) {
//            case 'Brand':
//                if (!in_array($lowerKey, $this->brandKeys)) {
//                    // BrandList::create(['key' => $lowerKey, 'name' => $cleanTag]);
//                    $this->brandKeys[] = $lowerKey;
//                    $this->info("Created new Brand: {$display}");
//                }
//                break;
            case 'Category':
                if (!in_array($lowerKey, $this->categoryKeys)) {
                    // Category::create(['key' => $lowerKey, 'name' => $cleanTag]);
                    $this->categoryKeys[] = $lowerKey;
                    $this->info("Created new Category: {$display}");
                }
                break;
//            case 'Object':
//                if (!in_array($lowerKey, $this->objectKeys)) {
//                    // LitterObject::create(['key' => $lowerKey, 'name' => $cleanTag]);
//                    $this->objectKeys[] = $lowerKey;
//                    $this->info("Created new Object: {$display}");
//                }
//                break;
//            case 'Material':
//                if (!in_array($lowerKey, $this->materialKeys)) {
//                    // Materials::create(['key' => $lowerKey, 'name' => $cleanTag]);
//                    $this->materialKeys[] = $lowerKey;
//                    $this->info("Created new Material: {$display}");
//                }
//                break;
//            case 'CustomTagNew':
//                // CustomTagNew::create(['key' => $lowerKey, 'name' => $cleanTag]);
//                $this->info("Created new CustomTagNew: {$display}");
//                break;
            default:
                break;
        }

        $this->loggedTags[] = $loggedKey;
    }
}

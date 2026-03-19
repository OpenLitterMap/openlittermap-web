<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * @deprecated
 */
class BrandValidator
{
    protected string $apiKey;
    protected array $results = [];

    public function __construct()
    {
        $this->apiKey = env('OPEN_AI_KEY');
        Storage::makeDirectory('brand-validations');
    }

    /**
     * Validate a single brand with comprehensive data
     */
    public function validateBrand(string $brandKey, array $brandData): array
    {
        // Extract objects - handle both old format (just objects) and new format (complete data)
        if (isset($brandData['objects'])) {
            $objects = $brandData['objects'];
        } else {
            // Fallback for simple array format
            $objects = $brandData;
            $brandData = ['objects' => $objects];
        }

        // Sort by count/percentage descending to prioritize high-frequency relationships
        if (is_array(reset($objects))) {
            // New format with detailed data
            uasort($objects, function($a, $b) {
                $aCount = $a['count'] ?? $a;
                $bCount = $b['count'] ?? $b;
                return $bCount <=> $aCount;
            });
        } else {
            // Simple format with just counts
            arsort($objects);
        }

        // Build comprehensive prompt with all available data
        $prompt = $this->buildPrompt($brandKey, $brandData);

        // Call OpenAI
        $response = Http::retry(3, 500)
            ->timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini', // Use cheaper model for simple binary classification
                'messages' => [
                    ['role' => 'system', 'content' => $this->getSystemPrompt()],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0,
                'response_format' => ['type' => 'json_object']
            ]);

        if (!$response->successful()) {
            throw new \Exception("API failed: " . $response->body());
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        $result = json_decode($content, true);

        if (!$result) {
            throw new \Exception("Invalid JSON response from API");
        }

        // Store individual result
        Storage::put(
            "brand-validations/{$brandKey}.json",
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $result;
    }

    /**
     * System prompt with comprehensive context about all possible objects
     */
    protected function getSystemPrompt(): string
    {
        // Load global objects catalog if available
        $catalogContext = $this->loadObjectsCatalog();

        return <<<PROMPT
You are validating brand-object relationships for OpenLitterMap, a litter tracking platform.

CRITICAL: Just because objects appear together in litter photos does NOT mean they're related.
The brand must PRODUCE the item or have their NAME/LOGO on it.

SYSTEM CONTEXT:
{$catalogContext}

VALIDATION RULES - BE STRICT:

✅ VALID only if:
- Brand manufactures this specific item
- Brand's name/logo appears on this item's packaging
- It's a retailer's own-brand/private label product

❌ INVALID if:
- Brand doesn't manufacture this type of product
- Item is from a completely different industry
- No brand name/logo would appear on this item

⚠️ PERCENTAGE INTERPRETATION - CRITICAL:
- **80-100% of brand photos**: EXTREMELY STRONG signal this is their product - likely VALID
- **60-79% of brand photos**: Strong indicator - investigate if it's their main product
- **40-59% of brand photos**: Moderate signal - could be their product line
- **20-39% of brand photos**: Weak signal - needs other evidence
- **<20% of photos**: Likely just coincidental litter - probably INVALID
- EXCEPTION: Large brands (1000+ photos) may have lower percentages but still be valid

SPECIAL CASES FOR HIGH PERCENTAGES:
- If ONE object appears in 90-100% of a brand's photos, it's almost certainly their product
- If a brand appears with ONLY one type of object, it's likely a specialized brand for that product
- Small brands (under 50 photos) with 100% association = very likely their product

BRAND TYPE GUIDELINES:

**APPAREL/SPORTSWEAR** (Nike, Adidas, Puma):
- VALID: clothing, shoes, tags, labels, shoe boxes
- INVALID: any beverages, food, tobacco, alcohol

**BEVERAGES** (Coca-Cola, Pepsi, Red Bull):
- VALID: their specific bottles, cans, cups, lids, labels
- INVALID: clothing, tobacco, unrelated food items

**TOBACCO** (Marlboro, Camel):
- VALID: cigarette boxes, cigarette packaging
- INVALID: beverages, food, clothing, anything non-tobacco

**FAST FOOD** (McDonald's, KFC, Burger King):
- VALID: their branded cups, food packaging, napkins, straws
- INVALID: cigarettes, alcohol (unless they sell it), clothing

**RETAILERS** (Tesco, Albert Heijn, Walmart):
- VALID: ONLY their own-brand products (store label)
- INVALID: other brands they sell but don't manufacture

**CONFECTIONERY** (Mars, Cadbury, Haribo):
- VALID: candy wrappers, chocolate packaging with their brand
- INVALID: beverages, cigarettes, non-confectionery items

IMPORTANT DISTINCTIONS:
- Adidas makes clothing → other.clothing is VALID
- Adidas doesn't make drinks → softdrinks.energy_can is INVALID (even if found together)
- McDonald's sells Coca-Cola → but softdrinks.tincan needs McDonald's branding to be VALID
- Retailers sell many brands → but only their OWN BRAND packaging is VALID

Return JSON with "valid" and "invalid" arrays.
When in doubt about high-percentage relationships (>80%), lean toward VALID.
When percentages are low (<20%), be very strict - mark as INVALID unless certain.
PROMPT;
    }

    /**
     * Load objects catalog for context
     */
    protected function loadObjectsCatalog(): string
    {
        // Find most recent objects catalog
        $catalogFiles = glob(storage_path('app/objects-catalog-*.csv'));
        if (empty($catalogFiles)) {
            return "No objects catalog available. Validate based on general knowledge.";
        }

        rsort($catalogFiles);
        $catalogPath = $catalogFiles[0];

        $handle = fopen($catalogPath, 'r');
        $headers = fgetcsv($handle); // Skip header

        $categories = [];
        $topObjects = [];
        $lineCount = 0;

        while ($row = fgetcsv($handle)) {
            if ($lineCount++ > 100) break; // Only include top 100 for context

            $category = $row[0];
            $object = $row[1];
            $count = $row[2];

            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            $categories[$category][] = $object;

            if ($lineCount <= 30) {
                $topObjects[] = "- {$category}.{$object} (found {$count} times)";
            }
        }

        fclose($handle);

        $context = "The system tracks " . count($categories) . " categories of litter:\n";
        $context .= implode(', ', array_keys($categories)) . "\n\n";
        $context .= "Most common objects in the system:\n";
        $context .= implode("\n", $topObjects) . "\n";

        return $context;
    }

    /**
     * Build comprehensive user prompt with all relationship data
     */
    protected function buildPrompt(string $brandKey, array $brandData): string
    {
        // Extract objects array - handle both old and new formats
        if (isset($brandData['objects']) && is_array($brandData['objects'])) {
            $objects = $brandData['objects'];
        } else {
            $objects = $brandData; // Fallback for simple array format
        }

        $objectList = [];
        $totalPhotoCount = $brandData['photo_count'] ?? 0;
        $categories = $brandData['categories'] ?? [];

        // Track highest percentage for emphasis
        $highestPercentage = 0;
        $dominantObjects = [];

        // Build detailed object list
        foreach ($objects as $objectKey => $data) {
            // Handle both formats: simple count or detailed array
            if (is_array($data)) {
                $count = $data['count'] ?? 0;
                $percentage = $data['percentage'] ?? 0;
            } else {
                // Simple count format
                $count = $data;
                $percentage = $totalPhotoCount > 0 ? round(($count / $totalPhotoCount) * 100, 1) : 0;
            }

            // Track highest percentage and dominant objects
            if ($percentage > $highestPercentage) {
                $highestPercentage = $percentage;
            }
            if ($percentage >= 80) {
                $dominantObjects[] = $objectKey;
            }

            // Add emphasis markers for high percentages
            $emphasis = '';
            if ($percentage >= 90) {
                $emphasis = ' ⚠️ DOMINANT';
            } elseif ($percentage >= 70) {
                $emphasis = ' ⚠️ HIGH';
            } elseif ($percentage >= 50) {
                $emphasis = ' ↑ NOTABLE';
            }

            $objectList[] = sprintf(
                "%-35s %4d times (%5.1f%% of brand photos)%s",
                $objectKey,
                $count,
                $percentage,
                $emphasis
            );
        }

        $objectsFormatted = implode("\n", $objectList);

        // Build category summary if available
        $categoryInfo = '';
        if (!empty($categories)) {
            $categoryInfo = "\nCategories found with this brand: " . implode(', ', $categories) . "\n";
        }

        // Include photo count context
        $contextInfo = '';
        if ($totalPhotoCount > 0) {
            $contextInfo = "\nThis brand appears in {$totalPhotoCount} photos total.\n";
        }

        // Add special attention for dominant relationships
        $dominantInfo = '';
        if (!empty($dominantObjects)) {
            $dominantInfo = "\n⚠️ ATTENTION: The following objects appear in 80%+ of this brand's photos:\n";
            foreach ($dominantObjects as $obj) {
                $dominantInfo .= "   - {$obj}\n";
            }
            $dominantInfo .= "This is a VERY STRONG indicator these are the brand's actual products!\n";
        }

        return <<<PROMPT
Brand: {$brandKey}
{$contextInfo}{$categoryInfo}{$dominantInfo}
Objects found with this brand (sorted by frequency):
{$objectsFormatted}

CRITICAL ANALYSIS REQUIRED:

1. PERCENTAGE ANALYSIS:
   - Highest percentage: {$highestPercentage}%
   - If any object appears in 80-100% of photos, it's EXTREMELY likely their product
   - For small brands (<50 photos), even 50%+ is a strong signal

2. BRAND IDENTIFICATION:
   - What type of brand is this? (beverage, tobacco, clothing, food, etc.)
   - Unknown brands with ONE dominant object type are likely specialized manufacturers

3. VALIDATION APPROACH:
   - Objects with 80%+ association: Default to VALID unless clearly impossible
   - Objects with <20% association: Default to INVALID unless you know they make it
   - Consider if this could be a regional/local brand you're unfamiliar with

Questions to ask:
1. Could "{$brandKey}" be a brand that manufactures the high-percentage items?
2. Are the high-percentage items all from the same product category?
3. For unknown brands: Does the pattern suggest what they might produce?

Examples:
- Unknown brand + 100% waterbottle = Likely a water brand → VALID
- Unknown brand + 95% cigarette boxes = Likely a tobacco brand → VALID
- Adidas + 15% energy cans = Sportswear brand doesn't make drinks → INVALID
- McDonald's + 75% food packaging = Fast food packaging → VALID

Return JSON:
{
  "brand": "{$brandKey}",
  "brand_type": "apparel|retailer|beverage|tobacco|food|other",
  "valid": ["items with high % OR items you KNOW they manufacture"],
  "invalid": ["items with low % AND no manufacturing relationship"],
  "notes": "If unknown brand, infer from high-percentage patterns"
}

PAY SPECIAL ATTENTION TO PERCENTAGE VALUES!
High percentages (80%+) are almost never coincidental.
PROMPT;
    }

    /**
     * Save all validation results
     */
    public function saveResults(array $results): void
    {
        $summary = [
            'validated_at' => now()->toIso8601String(),
            'total_brands' => count($results),
            'statistics' => $this->calculateStatistics($results),
            'results' => $results
        ];

        Storage::put(
            'brand-validations/summary.json',
            json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Calculate summary statistics
     */
    protected function calculateStatistics(array $results): array
    {
        $stats = [
            'total_valid' => 0,
            'total_invalid' => 0,
            'by_type' => [],
        ];

        foreach ($results as $result) {
            $stats['total_valid'] += count($result['valid'] ?? []);
            $stats['total_invalid'] += count($result['invalid'] ?? []);

            $type = $result['brand_type'] ?? 'unknown';
            if (!isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = 0;
            }
            $stats['by_type'][$type]++;
        }

        $stats['approval_rate'] = $stats['total_valid'] > 0
            ? round($stats['total_valid'] / ($stats['total_valid'] + $stats['total_invalid']) * 100, 1)
            : 0;

        return $stats;
    }

    /**
     * Generate BrandsConfig.php from validation results
     */
    public function generateConfig(string $outputPath): void
    {
        // Load all validation results
        $summaryPath = storage_path('app/brand-validations/summary.json');
        if (!file_exists($summaryPath)) {
            throw new \Exception("No validation results found. Run validation first.");
        }

        $summary = json_decode(file_get_contents($summaryPath), true);
        $results = $summary['results'] ?? [];

        $php = "<?php\n\nnamespace App\Tags;\n\n";
        $php .= "/**\n";
        $php .= " * AUTO-GENERATED Brand Configuration\n";
        $php .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $php .= " * Brands validated: " . count($results) . "\n";
        $php .= " * \n";
        $php .= " * Approval rate: " . ($summary['statistics']['approval_rate'] ?? 0) . "%\n";
        $php .= " * \n";
        $php .= " * ⚠️  REVIEW BEFORE MERGING INTO PRODUCTION\n";
        $php .= " */\n";
        $php .= "class BrandsConfigGenerated\n{\n";
        $php .= "    public const BRAND_OBJECTS = [\n";

        // Group by brand type for better organization
        $byType = [];
        foreach ($results as $brandKey => $result) {
            if (empty($result['valid'])) continue;

            $type = $result['brand_type'] ?? 'other';
            $byType[$type][$brandKey] = $result['valid'];
        }

        // Generate sections by type
        foreach (['retailer', 'beverage', 'food', 'tobacco', 'apparel', 'other'] as $type) {
            if (empty($byType[$type])) continue;

            $php .= "\n        // " . str_repeat('=', 50) . "\n";
            $php .= "        // " . strtoupper($type) . " BRANDS\n";
            $php .= "        // " . str_repeat('=', 50) . "\n\n";

            ksort($byType[$type]);
            foreach ($byType[$type] as $brandKey => $validObjects) {
                $php .= $this->formatBrandEntry($brandKey, $validObjects);
            }
        }

        $php .= "    ];\n}\n";

        file_put_contents($outputPath, $php);
    }

    /**
     * Format a single brand entry for the config file
     */
    protected function formatBrandEntry(string $brandKey, array $validObjects): string
    {
        // Group objects by category
        $byCategory = [];
        foreach ($validObjects as $objectFull) {
            if (strpos($objectFull, '.') === false) continue;

            [$category, $object] = explode('.', $objectFull, 2);
            $byCategory[$category][] = $object;
        }

        if (empty($byCategory)) {
            return '';
        }

        $php = "        '{$brandKey}' => [\n";

        ksort($byCategory);
        foreach ($byCategory as $category => $objects) {
            sort($objects);
            $objects = array_unique($objects);

            // Format objects list
            if (count($objects) <= 5) {
                // Single line for short lists
                $php .= "            '{$category}' => ['" . implode("', '", $objects) . "'],\n";
            } else {
                // Multi-line for long lists
                $php .= "            '{$category}' => [\n";
                foreach ($objects as $object) {
                    $php .= "                '{$object}',\n";
                }
                $php .= "            ],\n";
            }
        }

        $php .= "        ],\n\n";

        return $php;
    }
}

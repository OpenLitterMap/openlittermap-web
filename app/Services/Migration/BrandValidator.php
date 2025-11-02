<?php

namespace App\Services\Migration;

use App\Tags\TagsConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class BrandValidator
{
    protected string $apiKey;
    protected array $allCategories;

    public function __construct()
    {
        $this->apiKey = env('OPEN_AI_KEY');

        // Ensure directory exists
        Storage::makeDirectory('brands');

        // Load all categories from TagsConfig
        $this->allCategories = array_keys(TagsConfig::get());
    }

    /**
     * Validate a single brand's relationships
     */
    public function validateBrand(string $brandKey, array $relationships, int $total): array
    {
        // Filter low-quality relationships (≥1% OR ≥3 photos)
        $filtered = array_filter($relationships, fn($rel) =>
            $rel['percentage'] >= 1.0 || $rel['photo_count'] >= 3
        );

        if (empty($filtered)) {
            return [
                'brand' => $brandKey,
                'unknown_brand' => true,
                'validation_notes' => 'No relationships met quality threshold',
                'valid_relationships' => [],
                'excluded_with_reason' => []
            ];
        }

        // Extract unique categories and objects from this brand's data
        $observedContext = $this->extractObservedContext($filtered);

        // Build prompt with context
        $prompt = $this->buildPrompt($brandKey, $total, $filtered, $observedContext);

        // Call API
        $response = Http::retry(3, 500)
            ->timeout(45)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o',
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

        // Verify JSON integrity
        $data = $response->json();
        $content = data_get($data, 'choices.0.message.content');

        if (!$content) {
            throw new \Exception('Empty response content from API');
        }

        $result = json_decode($content, true);

        if (!$result) {
            throw new \Exception("Invalid JSON response: " . json_last_error_msg());
        }

        // Save individual result
        Storage::put("brands/{$brandKey}.json", json_encode($result, JSON_PRETTY_PRINT));

        return $result;
    }

    /**
     * Extract categories and objects observed for this specific brand
     */
    protected function extractObservedContext(array $relationships): array
    {
        $categories = [];
        $objects = [];

        foreach ($relationships as $rel) {
            $category = $rel['category'];
            $object = $rel['object'];

            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }

            $categories[$category][] = $object;
            $objects[] = "{$category}.{$object}";
        }

        return [
            'categories' => array_keys($categories),
            'objects_by_category' => $categories,
            'all_objects' => array_unique($objects),
        ];
    }

    /**
     * System prompt asking for manual expert review
     */
    protected function getSystemPrompt(): string
    {
        $totalCategories = count($this->allCategories);

        return <<<PROMPT
You are an expert reviewer for **OpenLitterMap**, a citizen science platform that tracks branded litter data.

---

### CONTEXT
OpenLitterMap has {$totalCategories} litter categories (e.g., softdrinks, food, smoking, coffee, alcohol, sanitary, other).
Each category contains object types (e.g., softdrinks.soda_can, food.wrapper, smoking.cigarette_box).

---

### YOUR TASK
Determine which objects legitimately belong to each brand, considering **parent-company and subsidiary products** that are included in the given list.

**CRITICAL:** The brand key you receive (e.g., "coke") refers to the **parent company** (Coca-Cola Company), not just the main product line.

Users often tag the parent company name for items from any of its sub-brands:
- "coke" → any Coca-Cola Company product (Dasani water, Powerade sports drinks, Fanta, Sprite, Minute Maid juice)
- "nestle" → any Nestlé product (KitKat candy, Nescafé coffee, Perrier water)
- "unilever" → any Unilever product (Dove soap, Lipton tea, Ben & Jerry's ice cream)

When reviewing, ask:
1. Does this brand **or its subsidiaries** produce/sell this object type?
2. Would a typical person reasonably tag this item with this brand name in the field?

---

### HOW TO DECIDE
- ✅ Be inclusive of the brand's full product portfolio (subsidiaries included)
- ❌ Exclude clear category mismatches (e.g., beverage brand → smoking product)
- Only include objects you are **confident** belong to the brand family
- Use occurrence rates as guidance:
  - >30% = strong evidence, likely valid
  - 10-30% = plausible, verify it fits brand family
  - <10% = likely noise unless clearly a brand product

---

### BRAND TYPE GUIDELINES
**Beverage** (Coca-Cola, Heineken, PepsiCo): cans, bottles, cups, lids, labels, water bottles, sports drinks, juices
**Tobacco** (Marlboro, Camel): cigarette boxes, butts, cellophane packaging
**Fast food** (McDonald's, Burger King): wrappers, boxes, napkins, cups, lids, straws, cutlery
**Retailers** (Tesco, Aldi, Walmart): packaging, plastic bags, receipts across many categories
**Clothing** (Nike, Adidas): clothing items, tags, packaging boxes
**Conglomerates** (Unilever, Nestlé, P&G): broad range across food, drinks, sanitary, other

---

### OUTPUT FORMAT
Return **valid JSON only** in this structure:
```json
{
  "brand": "brand_key",
  "unknown_brand": false,
  "brand_identity": {
    "recognized_as": "Full Brand or Company Name",
    "type": "beverage|tobacco|food|fast_food|retailer|clothing|conglomerate|other",
    "confidence": "high|medium|low",
    "subsidiaries_note": "Brief note about relevant sub-brands if applicable"
  },
  "valid_relationships": ["category.object", ...],
  "excluded_with_reason": {
    "category.object": "brief reason"
  },
  "validation_notes": "Summary of reasoning and subsidiaries considered"
}
```

**Confidence levels:**
- **high**: You recognize the brand/company and are certain about decisions
- **medium**: You recognize the brand but some decisions are borderline
- **low**: Unfamiliar brand or difficult to determine associations

If the brand is unrecognized, set `"unknown_brand": true` and explain briefly.

---

### EXAMPLES

**Brand: coke (Coca-Cola Company)**
✅ softdrinks.soda_can – Coca-Cola sodas (core product)
✅ softdrinks.water_bottle – Dasani, SmartWater (subsidiaries)
✅ softdrinks.sports_bottle – Powerade (subsidiary)
✅ softdrinks.juice_bottle – Minute Maid, Simply Orange (subsidiaries)
❌ alcohol.beer_can – Coca-Cola does not produce alcohol
❌ smoking.butts – Coincidental co-occurrence

**Brand: nestle (Nestlé S.A.)**
✅ food.wrapper – KitKat, candy bars
✅ coffee.cup – Nescafé products
✅ softdrinks.water_bottle – Perrier, Poland Spring (subsidiaries)
✅ food.packaging – Wide range of food products
❌ alcohol.beer_can – Not in portfolio
❌ smoking.butts – Unrelated

---

### SUMMARY
- The brand key represents the **parent company**, not just the main brand name
- Include only objects you are **confident** belong to the brand or its subsidiaries
- Exclude all others with a brief reason
- Respond **only with valid JSON**, no extra text

PROMPT;
    }

    /**
     * Build user prompt for specific brand with context
     */
    protected function buildPrompt(string $brandKey, int $total, array $relationships, array $context): string
    {
        $categoryCount = count($context['categories']);
        $objectCount = count($context['all_objects']);

        // List all observed objects for this brand
        $observedObjects = implode(', ', $context['all_objects']);

        // Build detailed relationship list
        $lines = [];
        foreach ($relationships as $i => $rel) {
            $lines[] = sprintf(
                "%d. %s.%s - %d photos (%.1f%%) - %d total occurrences",
                $i + 1,
                $rel['category'],
                $rel['object'],
                $rel['photo_count'],
                $rel['percentage'],
                $rel['total_occurrences'] ?? $rel['photo_count']
            );
        }

        $relationshipsList = implode("\n", $lines);

        return <<<PROMPT
═══════════════════════════════════════════════════════════
BRAND VALIDATION REQUEST
═══════════════════════════════════════════════════════════

BRAND: {$brandKey}

TOTAL OCCURRENCES IN DATABASE: {$total}

OBSERVED DATA SUMMARY:
- Categories observed: {$categoryCount}
- Unique objects observed: {$objectCount}
- All observed objects: {$observedObjects}

═══════════════════════════════════════════════════════════
RELATIONSHIPS TO REVIEW:
═══════════════════════════════════════════════════════════

{$relationshipsList}

═══════════════════════════════════════════════════════════
YOUR TASK:
═══════════════════════════════════════════════════════════

Please review the above data and determine with 100% certainty which category.object pairs legitimately belong to the brand "{$brandKey}".

Remember:
- Users may have made tagging mistakes
- Multiple unrelated items may appear in the same photo
- Only approve relationships you are absolutely certain about
- Consider: Does this brand actually produce/sell this object type?

Return your analysis as valid JSON only (no additional text).
PROMPT;
    }

    /**
     * Generate BrandsConfig PHP file
     */
    public function generateConfig(array $results, string $outputPath): void
    {
        $php = "<?php\n\nnamespace App\Tags;\n\n";
        $php .= "/**\n";
        $php .= " * AUTO-GENERATED by olm:validate-brands\n";
        $php .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $php .= " * Brands: " . count($results) . "\n";
        $php .= " * \n";
        $php .= " * ⚠️  REVIEW BEFORE MERGING INTO BrandsConfig.php\n";
        $php .= " */\n";
        $php .= "class BrandsConfigGenerated\n{\n";
        $php .= "    public const BRAND_OBJECTS = [\n";

        // Group by first letter
        $byLetter = [];
        foreach ($results as $brandKey => $result) {
            if ($result['unknown_brand'] ?? false) continue;
            if (empty($result['valid_relationships'])) continue;

            $letter = strtoupper(substr($brandKey, 0, 1));
            if (is_numeric($letter)) $letter = '#';
            $byLetter[$letter][$brandKey] = $result;
        }
        ksort($byLetter);

        // Generate entries
        foreach ($byLetter as $letter => $brands) {
            $php .= "\n        // {$letter}\n";
            ksort($brands);

            foreach ($brands as $brandKey => $result) {
                // Group by category
                $byCategory = [];
                foreach ($result['valid_relationships'] as $rel) {
                    [$category, $object] = explode('.', $rel);
                    $byCategory[$category][] = $object;
                }

                $php .= "        '{$brandKey}' => [\n";

                foreach ($byCategory as $category => $objects) {
                    // Deduplicate and sort
                    $objects = array_unique($objects);
                    sort($objects);

                    $php .= "            '{$category}' => ['" . implode("', '", $objects) . "'],\n";
                }

                $php .= "        ],\n";
            }
        }

        $php .= "    ];\n}\n";

        file_put_contents($outputPath, $php);
    }

    /**
     * Load brands from CSV (keeping original keys)
     */
    public function loadFromCSV(string $path): array
    {
        $brands = [];
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle);

        while ($row = fgetcsv($handle)) {
            $data = array_combine($headers, $row);
            $brandKey = $data['Brand']; // Keep original key exactly as-is

            if (!isset($brands[$brandKey])) {
                $brands[$brandKey] = [
                    'total' => (int) $data['Brand Total'],
                    'letter' => $data['Letter'],
                    'relationships' => []
                ];
            }

            $brands[$brandKey]['relationships'][] = [
                'category' => $data['Category'],
                'object' => $data['Object'],
                'photo_count' => (int) $data['Photo Count'],
                'total_occurrences' => (int) $data['Total Occurrences'],
                'percentage' => floatval(str_replace('%', '', $data['Percentage'])),
            ];
        }

        fclose($handle);
        return $brands;
    }
}

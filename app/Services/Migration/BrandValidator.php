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
     * Load brands from new CSV format with lift metrics
     */
    public function loadFromCSV(string $path): array
    {
        $brands = [];
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle);

        // Map column indices for faster access
        $columnIndex = array_flip($headers);

        while ($row = fgetcsv($handle)) {
            $brandKey = strtolower(trim($row[$columnIndex['Brand']]));

            if (!isset($brands[$brandKey])) {
                $brands[$brandKey] = [
                    'brand_key' => $brandKey,
                    'total' => 0,
                    'brand_photo_support' => 0,
                    'relationships' => []
                ];
            }

            // Update brand totals (use max value found)
            $brandPhotoSupport = (int) $row[$columnIndex['Brand_Photo_Support']];
            if ($brandPhotoSupport > $brands[$brandKey]['brand_photo_support']) {
                $brands[$brandKey]['brand_photo_support'] = $brandPhotoSupport;
                $brands[$brandKey]['total'] = $brandPhotoSupport; // Use photo support as total
            }

            // Add relationship with all metrics
            $brands[$brandKey]['relationships'][] = [
                'category' => $row[$columnIndex['Category']],
                'object' => $row[$columnIndex['Object']],
                'photo_count' => (int) $row[$columnIndex['Photo_Count']],
                'object_photo_support' => (int) $row[$columnIndex['Object_Photo_Support']],
                'p_obj_given_brand' => (float) $row[$columnIndex['P_obj_given_brand']],
                'p_obj_global' => (float) $row[$columnIndex['P_obj_global']],
                'lift' => (float) $row[$columnIndex['Lift']],
                'confidence' => $row[$columnIndex['Confidence']] ?? $this->calculateConfidenceFromMetrics(
                        (float) $row[$columnIndex['Lift']],
                        (int) $row[$columnIndex['Photo_Count']],
                        (float) $row[$columnIndex['P_obj_given_brand']]
                    ),
                'percentage' => (float) $row[$columnIndex['P_obj_given_brand']] * 100, // Convert to percentage for API
            ];
        }

        fclose($handle);
        return $brands;
    }

    /**
     * Calculate confidence from metrics if not in CSV
     */
    protected function calculateConfidenceFromMetrics(float $lift, int $photoCount, float $prob): string
    {
        if ($lift >= 3.0 && $photoCount >= 20 && $prob >= 0.3) return 'VERY_HIGH';
        if ($lift >= 2.0 && $photoCount >= 10 && $prob >= 0.2) return 'HIGH';
        if ($lift >= 1.5 && $photoCount >= 5 && $prob >= 0.1) return 'MEDIUM';
        if ($photoCount === 1) return 'NOISE';
        return 'LOW';
    }

    /**
     * Validate a single brand's relationships using lift-based filtering
     */
    public function validateBrand(string $brandKey, array $relationships, int $total): array
    {
        // Pre-filter using lift and support metrics
        $filtered = array_filter($relationships, function($rel) {
            // Primary criteria: high lift with reasonable support
            if ($rel['lift'] >= 3.0 && $rel['photo_count'] >= 5) return true;

            // Secondary: medium lift with good support
            if ($rel['lift'] >= 2.0 && $rel['photo_count'] >= 10) return true;

            // Tertiary: high probability even with lower lift
            if ($rel['p_obj_given_brand'] >= 0.20 && $rel['photo_count'] >= 10) return true;

            // Filter out noise
            return false;
        });

        if (empty($filtered)) {
            return [
                'brand' => $brandKey,
                'unknown_brand' => true,
                'validation_notes' => 'No relationships met quality threshold (lift/support too low)',
                'valid_relationships' => [],
                'excluded_with_reason' => []
            ];
        }

        // Sort by lift for better context in API
        usort($filtered, function($a, $b) {
            // Primary sort by lift
            $liftCompare = $b['lift'] <=> $a['lift'];
            if ($liftCompare !== 0) return $liftCompare;

            // Secondary sort by photo count
            return $b['photo_count'] <=> $a['photo_count'];
        });

        // Limit to top 30 relationships for API (cost control)
        if (count($filtered) > 30) {
            $filtered = array_slice($filtered, 0, 30);
        }

        // Extract unique categories and objects from this brand's data
        $observedContext = $this->extractObservedContext($filtered);

        // Build prompt with lift context
        $prompt = $this->buildPromptWithLift($brandKey, $total, $filtered, $observedContext);

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
                    ['role' => 'system', 'content' => $this->getSystemPromptWithLift()],
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

        // Add lift metrics to result
        $result['validation_metrics'] = [
            'relationships_evaluated' => count($filtered),
            'avg_lift' => round(array_sum(array_column($filtered, 'lift')) / count($filtered), 2),
            'avg_photo_support' => round(array_sum(array_column($filtered, 'photo_count')) / count($filtered), 1),
            'confidence_distribution' => array_count_values(array_column($filtered, 'confidence')),
        ];

        // Save individual result
        Storage::put("brands/{$brandKey}.json", json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

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
     * System prompt updated for lift-based validation
     */
    protected function getSystemPromptWithLift(): string
    {
        $totalCategories = count($this->allCategories);

        return <<<PROMPT
You are an expert reviewer for **OpenLitterMap**, a citizen science platform that tracks branded litter data.

---

### CONTEXT
OpenLitterMap has {$totalCategories} litter categories. Each category contains specific object types.

You are reviewing brand-object relationships that have been pre-filtered using **lift analysis**:
- **Lift** measures how much MORE likely an object appears with a brand vs randomly
- Lift > 1.0 means positive association
- Lift > 2.0 means strong association
- Lift > 3.0 means very strong association

The relationships you're reviewing have already passed quality filters, so they likely have merit.

---

### YOUR TASK
Validate which objects legitimately belong to each brand, considering:

1. **Parent Company Products**: The brand key often represents the parent company
   - "coke" = Coca-Cola Company (includes Dasani, Powerade, Fanta, Sprite, etc.)
   - "nestle" = Nestlé S.A. (includes KitKat, Nescafé, Perrier, etc.)
   - "unilever" = Unilever PLC (includes Dove, Lipton, Ben & Jerry's, etc.)

2. **High Lift Relationships**: High lift values (>3.0) suggest genuine relationships
   - These deserve extra consideration even if unexpected
   - Could indicate regional products or lesser-known subsidiaries

3. **Category Logic**: Consider if the category makes sense
   - Beverage brands → beverage containers ✅
   - Beverage brands → cigarettes ❌ (even with high lift)

---

### DECISION GUIDELINES

**APPROVE when:**
- Object type matches brand's product portfolio
- High lift (>3.0) with reasonable photo count (>10)
- Subsidiaries or regional products of the parent company
- Logical category match

**REJECT when:**
- Category completely mismatched (e.g., food brand → smoking products)
- Even with high lift, if logically impossible
- Clear data error or confusion

**CONFIDENCE LEVELS:**
- **very_high**: Recognized brand with clear, obvious relationships
- **high**: Recognized brand, relationships make sense
- **medium**: Some uncertainty about brand or borderline relationships
- **low**: Unfamiliar brand or many questionable relationships

---

### OUTPUT FORMAT
Return **valid JSON only**:
```json
{
  "brand": "brand_key",
  "unknown_brand": false,
  "brand_identity": {
    "recognized_as": "Full Brand/Company Name",
    "type": "beverage|tobacco|food|fast_food|retailer|clothing|conglomerate|other",
    "confidence": "very_high|high|medium|low",
    "subsidiaries_note": "Brief note about relevant sub-brands if applicable"
  },
  "valid_relationships": ["category.object", ...],
  "excluded_with_reason": {
    "category.object": "brief reason"
  },
  "validation_notes": "Summary of reasoning, noting any high-lift surprises"
}
```

Remember: High lift values are statistically significant. If you see lift>3.0 with good photo support,
there's likely a real relationship even if it's not immediately obvious.
PROMPT;
    }

    /**
     * Build user prompt with lift metrics highlighted
     */
    protected function buildPromptWithLift(string $brandKey, int $total, array $relationships, array $context): string
    {
        $categoryCount = count($context['categories']);
        $objectCount = count($context['all_objects']);

        // Calculate summary stats
        $highLiftCount = count(array_filter($relationships, fn($r) => $r['lift'] >= 3.0));
        $avgLift = array_sum(array_column($relationships, 'lift')) / count($relationships);
        $totalPhotoSupport = array_sum(array_column($relationships, 'photo_count'));

        // Build detailed relationship list with lift emphasis
        $lines = [];
        foreach ($relationships as $i => $rel) {
            $liftIndicator = '';
            if ($rel['lift'] >= 5.0) $liftIndicator = ' 🔥'; // Exceptional
            elseif ($rel['lift'] >= 3.0) $liftIndicator = ' ⭐'; // Very strong
            elseif ($rel['lift'] >= 2.0) $liftIndicator = ' ✓'; // Strong

            $lines[] = sprintf(
                "%2d. %-20s %-20s | Lift: %5.1f%s | Photos: %4d | Prob: %5.1f%%",
                $i + 1,
                $rel['category'],
                $rel['object'],
                $rel['lift'],
                $liftIndicator,
                $rel['photo_count'],
                $rel['percentage']
            );
        }

        $relationshipsList = implode("\n", $lines);

        return <<<PROMPT
═══════════════════════════════════════════════════════════
BRAND VALIDATION WITH LIFT ANALYSIS
═══════════════════════════════════════════════════════════

BRAND: {$brandKey}

STATISTICS:
- Total brand occurrences: {$total} photos
- Relationships to validate: " . count($relationships) . "
- High-lift relationships (>3.0): {$highLiftCount}
- Average lift score: " . number_format($avgLift, 1) . "
- Total photo evidence: {$totalPhotoSupport}

LIFT LEGEND:
🔥 = Exceptional (5.0+) - Almost certainly valid
⭐ = Very Strong (3.0+) - Likely valid unless illogical
✓ = Strong (2.0+) - Consider if category matches

═══════════════════════════════════════════════════════════
RELATIONSHIPS (sorted by lift):
═══════════════════════════════════════════════════════════

{$relationshipsList}

═══════════════════════════════════════════════════════════
YOUR TASK:
═══════════════════════════════════════════════════════════

Review the above relationships for brand "{$brandKey}".

Pay special attention to high-lift relationships (🔥 and ⭐) as these show
statistically significant associations in our 500,000+ photo dataset.

Consider:
1. Does this brand (or its parent company) produce these object types?
2. High lift values suggest real relationships - is there a subsidiary or regional product?
3. Category mismatches should still be rejected even with high lift

Return your validation as JSON only (no additional text).
PROMPT;
    }

    /**
     * Generate BrandsConfig PHP file with confidence indicators
     */
    public function generateConfig(array $results, string $outputPath): void
    {
        $php = "<?php\n\nnamespace App\Tags;\n\n";
        $php .= "/**\n";
        $php .= " * AUTO-GENERATED by olm:validate-brands\n";
        $php .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $php .= " * Brands validated: " . count($results) . "\n";
        $php .= " * \n";
        $php .= " * Confidence levels:\n";
        $php .= " *   [no marker] = very_high or high confidence\n";
        $php .= " *   // MEDIUM  = medium confidence, review recommended\n";
        $php .= " *   // ⚠️ LOW  = low confidence, manual review required\n";
        $php .= " * \n";
        $php .= " * ⚠️  REVIEW ALL ENTRIES BEFORE MERGING INTO BrandsConfig.php\n";
        $php .= " */\n";
        $php .= "class BrandsConfigGenerated\n{\n";
        $php .= "    public const BRAND_OBJECTS = [\n";

        // Group by confidence level for easier review
        $byConfidence = [
            'very_high' => [],
            'high' => [],
            'medium' => [],
            'low' => [],
            'unknown' => []
        ];

        foreach ($results as $brandKey => $result) {
            if ($result['unknown_brand'] ?? false) {
                $byConfidence['unknown'][$brandKey] = $result;
                continue;
            }
            if (empty($result['valid_relationships'])) continue;

            $confidence = $result['brand_identity']['confidence'] ?? 'unknown';
            $byConfidence[$confidence][$brandKey] = $result;
        }

        // Generate high confidence brands first
        if (!empty($byConfidence['very_high']) || !empty($byConfidence['high'])) {
            $php .= "\n        // ═══════════════════════════════════════\n";
            $php .= "        // HIGH CONFIDENCE BRANDS\n";
            $php .= "        // ═══════════════════════════════════════\n\n";

            $highConfidence = array_merge($byConfidence['very_high'], $byConfidence['high']);
            ksort($highConfidence);

            foreach ($highConfidence as $brandKey => $result) {
                $php .= $this->generateBrandEntry($brandKey, $result, '');
            }
        }

        // Medium confidence
        if (!empty($byConfidence['medium'])) {
            $php .= "\n        // ═══════════════════════════════════════\n";
            $php .= "        // MEDIUM CONFIDENCE - REVIEW RECOMMENDED\n";
            $php .= "        // ═══════════════════════════════════════\n\n";

            ksort($byConfidence['medium']);
            foreach ($byConfidence['medium'] as $brandKey => $result) {
                $php .= $this->generateBrandEntry($brandKey, $result, 'MEDIUM');
            }
        }

        // Low confidence
        if (!empty($byConfidence['low'])) {
            $php .= "\n      // ═══════════════════════════════════════\n";
            $php .= "        // ⚠️  LOW CONFIDENCE - MANUAL REVIEW REQUIRED\n";
            $php .= "        // ═══════════════════════════════════════\n\n";

            ksort($byConfidence['low']);
            foreach ($byConfidence['low'] as $brandKey => $result) {
                $php .= $this->generateBrandEntry($brandKey, $result, '⚠️ LOW');
            }
        }

        $php .= "    ];\n}\n";

        file_put_contents($outputPath, $php);
    }

    /**
     * Generate individual brand entry for config
     */
    protected function generateBrandEntry(string $brandKey, array $result, string $confidenceMarker): string
    {
        $php = '';

        // Add confidence comment if needed
        if ($confidenceMarker) {
            $recognizedAs = $result['brand_identity']['recognized_as'] ?? 'Unknown';
            $php .= "        // {$confidenceMarker}: {$recognizedAs}\n";
        }

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

        return $php;
    }
}

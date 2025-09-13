<?php

namespace App\Services\Tags;

use App\Models\Photo;
use Illuminate\Support\Facades\Log;

class TagMigrationVerifier
{
    protected UpdateTagsService $updateTagsService;
    protected ClassifyTagsService $classifyTags;

    public function __construct(
        UpdateTagsService $updateTagsService,
        ClassifyTagsService $classifyTags
    ) {
        $this->updateTagsService = $updateTagsService;
        $this->classifyTags = $classifyTags;
    }

    /**
     * Verify a single photo's migration
     * Note: Requires getTags() and parseTags() to be public in UpdateTagsService
     */
    public function verifyPhoto(Photo $photo): array
    {
        // Get original tags using same method as migration
        // NOTE: These methods need to be public in UpdateTagsService
        [$originalTags, $customTagsOld] = $this->updateTagsService->getTags($photo);

        // Parse expected tags without writing
        $expected = $this->updateTagsService->parseTags($originalTags, $customTagsOld);

        // Get actual migrated data
        $actual = $this->getActualMigratedData($photo);

        // Calculate differences
        $diffs = $this->calculateDifferences($expected, $actual);

        // Check for specific issues
        $issues = $this->identifyIssues($photo, $expected, $actual, $diffs);

        return [
            'photo_id' => $photo->id,
            'expected' => $this->summarizeExpected($expected),
            'actual' => $actual,
            'diffs' => $diffs,
            'issues' => $issues,
            'passed' => empty($issues)
        ];
    }

    /**
     * Verify all photos for a user
     */
    public function verifyUser(int $userId, int $limit = null): array
    {
        $query = Photo::where('user_id', $userId)
            ->whereNotNull('migrated_at')
            ->orderBy('id');

        if ($limit) {
            $query->limit($limit);
        }

        $results = [
            'user_id' => $userId,
            'total_photos' => 0,
            'passed' => 0,
            'failed' => 0,
            'issues_summary' => [],
            'deprecated_tags_used' => [],
            'autocreated_objects' => [],
            'failures' => []
        ];

        foreach ($query->cursor() as $photo) {
            $verification = $this->verifyPhoto($photo);
            $results['total_photos']++;

            if ($verification['passed']) {
                $results['passed']++;
            } else {
                $results['failed']++;
                $results['failures'][] = [
                    'photo_id' => $photo->id,
                    'issues' => $verification['issues'],
                    'diffs' => $verification['diffs']
                ];
            }

            // Aggregate issue types
            foreach ($verification['issues'] as $issue) {
                $type = $issue['type'];
                $results['issues_summary'][$type] = ($results['issues_summary'][$type] ?? 0) + 1;
            }
        }

        // Get additional insights
        $results['deprecated_tags_used'] = $this->getDeprecatedTagsUsed($userId);
        $results['autocreated_objects'] = $this->getAutocreatedObjectsForUser($userId);

        return $results;
    }

    protected function getActualMigratedData(Photo $photo): array
    {
        $photoTags = $photo->photoTags()->with('extraTags')->get();

        $actual = [
            'objects' => 0,
            'materials' => 0,
            'brands' => 0,
            'custom_tags' => 0,
            'total_tags' => 0,
            'categories' => []
        ];

        foreach ($photoTags as $pt) {
            // Count objects
            if ($pt->litter_object_id) {
                $actual['objects'] += $pt->quantity;
            }

            // Track categories
            if ($pt->category_id) {
                $actual['categories'][$pt->category_id] =
                    ($actual['categories'][$pt->category_id] ?? 0) + $pt->quantity;
            }

            // Count primary custom tags
            if ($pt->custom_tag_primary_id) {
                $actual['custom_tags'] += $pt->quantity;
            }

            // Count extra tags
            foreach ($pt->extraTags as $extra) {
                switch ($extra->tag_type) {
                    case 'material':
                        $actual['materials'] += $extra->quantity;
                        break;
                    case 'brand':
                        $actual['brands'] += $extra->quantity;
                        break;
                    case 'custom_tag':
                        $actual['custom_tags'] += $extra->quantity;
                        break;
                }
            }
        }

        $actual['total_tags'] = $actual['objects'] + $actual['materials']
            + $actual['brands'] + $actual['custom_tags'];

        // Include summary for comparison
        if ($photo->summary) {
            $actual['summary_totals'] = $photo->summary['totals'] ?? [];
        }

        return $actual;
    }

    protected function summarizeExpected(array $expected): array
    {
        $summary = [
            'objects' => 0,
            'materials' => 0,
            'brands' => 0,
            'custom_tags' => 0,
            'categories' => []
        ];

        // Count from groups
        foreach ($expected['groups'] as $categoryKey => $group) {
            $categoryCount = 0;

            foreach ($group['objects'] as $obj) {
                $qty = (int)($obj['quantity'] ?? 0);
                $summary['objects'] += $qty;
                $categoryCount += $qty;

                // Count expected materials (one per object quantity)
                foreach (($obj['materials'] ?? []) as $_) {
                    $summary['materials'] += $qty;
                }
            }

            foreach ($group['brands'] as $brand) {
                $summary['brands'] += (int)($brand['quantity'] ?? 0);
            }

            if (!empty($group['category_id'])) {
                $summary['categories'][$group['category_id']] = $categoryCount;
            }
        }

        // Add global brands (distributed to all groups)
        $globalBrandCount = array_sum(array_column($expected['globalBrands'] ?? [], 'quantity'));
        $groupCount = max(1, count($expected['groups']));
        if ($globalBrandCount > 0 && $groupCount > 0) {
            $summary['brands'] += $globalBrandCount;
        }

        // Sum quantities
        $summary['custom_tags'] = array_sum(
            array_map(fn($c) => (int)($c['quantity'] ?? 1), $expected['topLevelCustomTags'] ?? [])
        );

        return $summary;
    }

    protected function calculateDifferences(array $expected, array $actual): array
    {
        $expectedSummary = $this->summarizeExpected($expected);

        return [
            'objects' => $actual['objects'] - $expectedSummary['objects'],
            'materials' => $actual['materials'] - $expectedSummary['materials'],
            'brands' => $actual['brands'] - $expectedSummary['brands'],
            'custom_tags' => $actual['custom_tags'] - $expectedSummary['custom_tags'],
            'total_delta' => ($actual['objects'] + $actual['materials'] + $actual['brands'] + $actual['custom_tags'])
                - ($expectedSummary['objects'] + $expectedSummary['materials'] + $expectedSummary['brands'] + $expectedSummary['custom_tags'])
        ];
    }

    protected function identifyIssues(Photo $photo, array $expected, array $actual, array $diffs): array
    {
        $issues = [];

        // Check for count mismatches
        foreach (['objects', 'materials', 'brands', 'custom_tags'] as $type) {
            if ($diffs[$type] !== 0) {
                $issues[] = [
                    'type' => "{$type}_mismatch",
                    'message' => "{$type} count mismatch",
                    'expected' => $this->summarizeExpected($expected)[$type],
                    'actual' => $actual[$type],
                    'diff' => $diffs[$type]
                ];
            }
        }

        // Check ALL summary fields for consistency
        if (isset($actual['summary_totals'])) {
            $st = $actual['summary_totals'];

            $checks = [
                ['type' => 'summary_objects_mismatch', 'key' => 'total_objects', 'actual' => $actual['objects']],
                ['type' => 'summary_brands_mismatch', 'key' => 'brands', 'actual' => $actual['brands']],
                ['type' => 'summary_materials_mismatch', 'key' => 'materials', 'actual' => $actual['materials']],
                ['type' => 'summary_customs_mismatch', 'key' => 'custom_tags', 'actual' => $actual['custom_tags']],
            ];

            foreach ($checks as $c) {
                $summaryValue = (int)($st[$c['key']] ?? 0);
                if ($summaryValue !== (int)$c['actual']) {
                    $issues[] = [
                        'type' => $c['type'],
                        'message' => "Summary {$c['key']} doesn't match actual",
                        'summary' => $summaryValue,
                        'actual' => (int)$c['actual'],
                    ];
                }
            }
        }

        // Check for brands-only / custom-only semantic violations
        $hasObjects = !empty($expected['groups']) &&
            collect($expected['groups'])->pluck('objects')->flatten()->isNotEmpty();
        $hasBrands = !empty($expected['globalBrands']);

        if (!$hasObjects && $hasBrands && empty($expected['topLevelCustomTags'])) {
            // This should be a brands-only photo
            $photoTags = $photo->photoTags;
            if ($photoTags->whereNotNull('litter_object_id')->isNotEmpty()) {
                $issues[] = [
                    'type' => 'brands_only_violation',
                    'message' => 'Expected brands-only but found objects'
                ];
            }
        }

        return $issues;
    }

    protected function getDeprecatedTagsUsed(int $userId): array
    {
        $deprecated = [];
        $photos = Photo::where('user_id', $userId)->get();

        foreach ($photos as $photo) {
            $tags = $photo->tags() ?? [];
            foreach ($tags as $category => $items) {
                foreach ($items as $tag => $qty) {
                    if ($this->classifyTags::normalizeDeprecatedTag($tag) !== null) {
                        $deprecated[$tag] = ($deprecated[$tag] ?? 0) + 1;
                    }
                }
            }
        }

        return $deprecated;
    }

    /**
     * Get autocreated objects actually used by this user
     */
    protected function getAutocreatedObjectsForUser(int $userId): array
    {
        // Collect all object keys this user's photos reference
        $usedKeys = [];

        $photos = Photo::where('user_id', $userId)
            ->whereNotNull('migrated_at')
            ->cursor();

        foreach ($photos as $photo) {
            // NOTE: Requires getTags() and parseTags() to be public
            [$legacy, $customs] = $this->updateTagsService->getTags($photo);
            $parsed = $this->updateTagsService->parseTags($legacy, $customs);

            foreach ($parsed['groups'] as $group) {
                foreach ($group['objects'] as $obj) {
                    if (isset($obj['key'])) {
                        $usedKeys[] = $obj['key'];
                    }
                }
            }
        }

        $usedKeys = array_values(array_unique($usedKeys));

        if (empty($usedKeys)) {
            return [];
        }

        // Return only crowdsourced objects that this user actually used
        return \App\Models\Litter\Tags\LitterObject::where('crowdsourced', true)
            ->whereIn('key', $usedKeys)
            ->pluck('key')
            ->toArray();
    }
}

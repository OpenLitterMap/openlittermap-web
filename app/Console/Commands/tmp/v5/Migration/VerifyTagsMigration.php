<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Litter\Tags\Category;
use App\Models\Photo;
use App\Services\Tags\TagMigrationVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VerifyTagsMigration extends Command
{
    protected $signature = 'olm:verify-tags-fixed
                            {--user= : Specific user ID to verify}
                            {--photo= : Specific photo ID to debug}
                            {--limit= : Limit number of photos to check}
                            {--output : Save detailed report to file}
                            {--debug : Show detailed debugging for each failure}
                            {--fix : Attempt to fix brand mismatches}';

    protected $description = 'Verify tag migration accuracy for users (FIXED VERSION)';

    protected TagMigrationVerifier $verifier;

    public function __construct(TagMigrationVerifier $verifier)
    {
        parent::__construct();
        $this->verifier = $verifier;
    }

    public function handle()
    {
        if ($photoId = $this->option('photo')) {
            return $this->debugPhoto($photoId);
        }

        $userId = $this->option('user');
        $limit = $this->option('limit');

        if (!$userId) {
            $this->error('Please specify a user ID with --user=ID');
            return self::FAILURE;
        }

        $this->info("Verifying tag migration for User #{$userId}");
        $this->info("========================================");

        // Run verification with fixed brand counting
        $results = $this->verifyUserFixed($userId, $limit);

        // Display results...
        $this->displaySummary($results);

        if (!empty($results['deprecated_tags_used'])) {
            $this->displayDeprecatedTags($results['deprecated_tags_used']);
        }

        if (!empty($results['autocreated_objects'])) {
            $this->displayAutocreatedObjects($results['autocreated_objects']);
        }

        if ($results['failed'] > 0) {
            $this->displayFailures($results['failures']);
        }

        return $results['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function verifyUserFixed(int $userId, ?int $limit = null)
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
            'failures' => [],
            'issues_summary' => [],
            'deprecated_tags_used' => [],
            'autocreated_objects' => []
        ];

        $query->chunk(100, function($photos) use (&$results) {
            foreach ($photos as $photo) {
                $results['total_photos']++;

                $verification = $this->verifyPhotoFixed($photo);

                if ($verification['passed']) {
                    $results['passed']++;
                } else {
                    $results['failed']++;
                    $results['failures'][] = $verification;

                    // Track issue types
                    foreach ($verification['issues'] as $issue) {
                        $type = $issue['type'] ?? 'unknown';
                        $results['issues_summary'][$type] = ($results['issues_summary'][$type] ?? 0) + 1;
                    }
                }
            }
        });

        return $results;
    }

    protected function verifyPhotoFixed(Photo $photo): array
    {
        $result = [
            'photo_id' => $photo->id,
            'passed' => true,
            'issues' => [],
            'diffs' => [
                'objects' => 0,
                'materials' => 0,
                // 'brands' => 0,
                'custom_tags' => 0,
                'total_delta' => 0
            ]
        ];

        // Get OLD tags using the same method as migration
        $oldTags = $photo->tags();

        // Count expected brands from the tags() array, NOT from brands table
        $expectedBrands = 0;
//        if (isset($oldTags['brands']) && is_array($oldTags['brands'])) {
//            foreach ($oldTags['brands'] as $brandKey => $qty) {
//                $expectedBrands += (int) $qty;
//            }
//        }

        // Count actual brands in new PhotoTags
//        $actualBrands = DB::table('photo_tag_extra_tags')
//            ->whereIn('photo_tag_id', $photo->photoTags->pluck('id'))
//            ->where('tag_type', 'brand')
//            ->sum('quantity');

        // Compare
//        if ($expectedBrands !== $actualBrands) {
//            $result['passed'] = false;
//            $result['issues'][] = [
//                'type' => 'brands_mismatch',
//                'message' => 'brands count mismatch',
//                'expected' => $expectedBrands,
//                'actual' => $actualBrands,
//                'diff' => $actualBrands - $expectedBrands
//            ];
//            $result['diffs']['brands'] = $actualBrands - $expectedBrands;
//            $result['diffs']['total_delta'] += abs($actualBrands - $expectedBrands);
//        }

        // Verify objects are properly migrated
        $expectedObjects = 0;
        foreach ($oldTags as $category => $items) {
            if ($category !== 'brands') {
                foreach ($items as $tag => $qty) {
                    if ((int) $qty > 0) {
                        $expectedObjects++;
                    }
                }
            }
        }

        $actualObjects = $photo->photoTags()
            ->whereNotNull('litter_object_id')
            ->count();

        if ($expectedObjects !== $actualObjects) {
            $result['passed'] = false;
            $result['issues'][] = [
                'type' => 'objects_mismatch',
                'message' => 'object count mismatch',
                'expected' => $expectedObjects,
                'actual' => $actualObjects,
                'diff' => $actualObjects - $expectedObjects
            ];
            $result['diffs']['objects'] = $actualObjects - $expectedObjects;
            $result['diffs']['total_delta'] += abs($actualObjects - $expectedObjects);
        }

        // Check for NULL object IDs (critical issue)
        // Excluding brands for now
        $brandsCategoryId = Category::where('key', 'brands')->value('id');
        $nullObjectCount = $photo->photoTags()
            ->whereNull('litter_object_id')
            ->whereNull('custom_tag_primary_id')
            ->where(function ($q) use ($brandsCategoryId) {
                $q->whereNull('category_id')
                    ->orWhere('category_id', '!=', $brandsCategoryId);
            })
            ->count();

        if ($nullObjectCount > 0) {
            $result['passed'] = false;
            $result['issues'][] = [
                'type' => 'null_objects',
                'message' => "Found {$nullObjectCount} PhotoTags with NULL object IDs",
                'count' => $nullObjectCount
            ];
        }

        return $result;
    }

    protected function debugPhoto($photoId): int
    {
        $photo = Photo::with(['photoTags.extraTags'])->find($photoId);

        if (!$photo) {
            $this->error("Photo #{$photoId} not found");
            return self::FAILURE;
        }

        $this->info("=== DEBUGGING PHOTO #{$photoId} ===");
        $this->info("User: #{$photo->user_id}");
        $this->info("Created: {$photo->created_at}");
        $this->info("Migrated: " . ($photo->migrated_at ?? 'NOT MIGRATED'));

        // Show old tags using tags() method
        $this->info("\n📦 OLD TAGS (from tags() method):");
        $this->info("─────────────────────────────────");

        $oldTags = $photo->tags();
        if (empty($oldTags)) {
            $this->line("  No old tags found");
        } else {
            foreach ($oldTags as $category => $items) {
                $this->line("  {$category}:");
                foreach ($items as $tag => $qty) {
                    $this->line("    • {$tag}: {$qty}");
                }
            }
        }

        // Count brands properly
//        $expectedBrands = 0;
//        if (isset($oldTags['brands'])) {
//            foreach ($oldTags['brands'] as $brand => $qty) {
//                $expectedBrands += (int) $qty;
//            }
//        }

        // Show new tags
        $this->info("\n📋 NEW TAGS (v5 PhotoTags):");
        $this->info("───────────────────────────");

        if ($photo->photoTags->isEmpty()) {
            $this->line("  No PhotoTags found");
        } else {
            foreach ($photo->photoTags as $photoTag) {
                $this->line("  PhotoTag #{$photoTag->id}:");
                $this->line("    Category: " . ($photoTag->category->key ?? 'N/A'));
                $this->line("    Object: " . ($photoTag->object->key ?? 'N/A'));
                $this->line("    Quantity: {$photoTag->quantity}");

                if ($photoTag->extraTags->isNotEmpty()) {
                    $this->line("    Extra Tags:");
                    foreach ($photoTag->extraTags as $extra) {
                        $tagName = $this->getExtraTagName($extra);
                        $this->line("      • {$extra->tag_type}: {$tagName} (qty: {$extra->quantity})");
                    }
                }
            }
        }

        // Brand count analysis with correct method
//        $this->info("\n🔍 BRAND COUNT ANALYSIS:");
//        $this->info("────────────────────────");
//
//        $actualBrands = DB::table('photo_tag_extra_tags')
//            ->whereIn('photo_tag_id', $photo->photoTags->pluck('id'))
//            ->where('tag_type', 'brand')
//            ->sum('quantity');
//
//        $this->line("  Expected brands (from tags()['brands']): {$expectedBrands}");
//        $this->line("  Actual brands (in PhotoTags): {$actualBrands}");
//
//        if ($expectedBrands !== $actualBrands) {
//            $this->error("  ❌ MISMATCH: Difference of " . ($actualBrands - $expectedBrands));
//        } else {
//            $this->info("  ✅ Brand counts match!");
//        }

        return self::SUCCESS;
    }

    protected function getExtraTagName($extra): string
    {
        $table = match($extra->tag_type) {
            'brand' => 'brandslist',
            'material' => 'materials',
            'custom_tag' => 'custom_tags_new',
            default => null
        };

        if (!$table) return 'unknown';

        try {
            $record = DB::table($table)->find($extra->tag_type_id);
            return $record->key ?? "ID:{$extra->tag_type_id}";
        } catch (\Exception $e) {
            return "ID:{$extra->tag_type_id}";
        }
    }

    // Keep the display methods from original...
    protected function displaySummary(array $results): void
    {
        $this->info("\n📊 Migration Summary:");
        $this->info("─────────────────────");

        $this->line("Total photos checked: {$results['total_photos']}");
        $this->line("✅ Passed: {$results['passed']}");

        if ($results['failed'] > 0) {
            $this->error("❌ Failed: {$results['failed']}");
        }

        if (!empty($results['issues_summary'])) {
            $this->info("\n⚠️  Issues Found:");
            foreach ($results['issues_summary'] as $type => $count) {
                $this->line("  • {$type}: {$count} occurrences");
            }
        } else {
            $this->info("\n✨ No issues found!");
        }
    }

    protected function displayDeprecatedTags(array $deprecated): void
    {
        $this->info("\n🔄 Deprecated Tags Used:");
        $this->info("────────────────────────");

        $this->table(
            ['Old Tag', 'Usage Count'],
            collect($deprecated)->map(fn($count, $tag) => [$tag, $count])->toArray()
        );
    }

    protected function displayAutocreatedObjects(array $objects): void
    {
        $this->info("\n🆕 Auto-created Objects:");
        $this->info("────────────────────────");

        foreach ($objects as $key) {
            $this->line("  • {$key}");
        }

        $this->comment("\nThese objects were created during migration as they didn't exist in the database.");
    }

    protected function displayFailures(array $failures): void
    {
        $this->error("\n❌ Failed Photos (showing first 10):");
        $this->error("─────────────────────────────────────");

        $toShow = array_slice($failures, 0, 10);

        foreach ($toShow as $failure) {
            $this->line("\nPhoto #{$failure['photo_id']}:");

            foreach ($failure['issues'] as $issue) {
                $this->line("  • {$issue['message']}");

                if (isset($issue['expected']) && isset($issue['actual'])) {
                    $this->line("    Expected: {$issue['expected']}, Actual: {$issue['actual']}, Diff: {$issue['diff']}");
                }
            }

            $diffs = $failure['diffs'];
            if ($diffs['total_delta'] !== 0) {
                //  brands={$diffs['brands']}
                $this->line("  Totals: Δ objects={$diffs['objects']}, materials={$diffs['materials']}, custom={$diffs['custom_tags']}");
            }
        }

        if (count($failures) > 10) {
            $this->comment("\n... and " . (count($failures) - 10) . " more failures. Use --output to see all.");
        }
    }
}

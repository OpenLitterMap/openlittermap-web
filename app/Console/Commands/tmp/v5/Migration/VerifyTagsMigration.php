<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Services\Tags\TagMigrationVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class VerifyTagsMigration extends Command
{
    protected $signature = 'olm:verify-tags
                            {--user= : Specific user ID to verify}
                            {--limit= : Limit number of photos to check}
                            {--output : Save detailed report to file}';

    protected $description = 'Verify tag migration accuracy for users';

    protected TagMigrationVerifier $verifier;

    public function __construct(TagMigrationVerifier $verifier)
    {
        parent::__construct();
        $this->verifier = $verifier;
    }

    public function handle()
    {
        $userId = $this->option('user');
        $limit = $this->option('limit');

        if (!$userId) {
            $this->error('Please specify a user ID with --user=ID');
            return self::FAILURE;
        }

        $this->info("Verifying tag migration for User #{$userId}");
        $this->info("========================================");

        // Run verification
        $results = $this->verifier->verifyUser($userId, $limit);

        // Display summary
        $this->displaySummary($results);

        // Show deprecated tags if any
        if (!empty($results['deprecated_tags_used'])) {
            $this->displayDeprecatedTags($results['deprecated_tags_used']);
        }

        // Show autocreated objects if any
        if (!empty($results['autocreated_objects'])) {
            $this->displayAutocreatedObjects($results['autocreated_objects']);
        }

        // Show failures if any
        if ($results['failed'] > 0) {
            $this->displayFailures($results['failures']);
        }

        // Save detailed report if requested
        if ($this->option('output')) {
            $this->saveReport($results);
        }

        // Return exit code based on success
        return $results['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

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

            // Show diffs summary
            $diffs = $failure['diffs'];
            if ($diffs['total_delta'] !== 0) {
                $this->line("  Totals: Δ objects={$diffs['objects']}, materials={$diffs['materials']}, brands={$diffs['brands']}, custom={$diffs['custom_tags']}");
            }
        }

        if (count($failures) > 10) {
            $this->comment("\n... and " . (count($failures) - 10) . " more failures. Use --output to see all.");
        }
    }

    protected function saveReport(array $results): void
    {
        $timestamp = now()->format('Y-m-d_His');
        $filename = "migration_verify_user_{$results['user_id']}_{$timestamp}.json";
        $path = "migration_reports/{$filename}";

        Storage::disk('local')->put($path, json_encode($results, JSON_PRETTY_PRINT));

        $this->info("\n📁 Detailed report saved to:");
        $this->line("   storage/app/{$path}");
    }
}

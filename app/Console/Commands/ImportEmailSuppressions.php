<?php

namespace App\Console\Commands;

use App\Models\EmailSuppression;
use Illuminate\Console\Command;

/**
 * Imports the SES account-level suppression list (region-wide hard
 * bounces/complaints) into email_suppressions. Catches addresses that died
 * before the SNS webhook existed.
 *
 * Produce the input with:
 *   aws sesv2 list-suppressed-destinations --region eu-west-1 \
 *     --page-size 1000 --output json > ses-suppressed.json
 *
 * Upserts by email (source=backfill), preserves complained over bounced,
 * idempotent on re-run.
 */
class ImportEmailSuppressions extends Command
{
    protected $signature = 'email:import-suppressions {file : Path to the ses-suppressed.json export}';

    protected $description = 'Import the SES account-level suppression list into email_suppressions.';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("File not found or unreadable: {$path}");

            return self::FAILURE;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || ! isset($data['SuppressedDestinationSummaries'])) {
            $this->error('Invalid file: expected a JSON object with SuppressedDestinationSummaries[].');

            return self::FAILURE;
        }

        $imported = 0;

        foreach ($data['SuppressedDestinationSummaries'] as $row) {
            $email = $row['EmailAddress'] ?? null;
            $reason = $this->mapReason($row['Reason'] ?? '');

            if (! $email || ! $reason) {
                continue;
            }

            EmailSuppression::suppress($email, $reason, 'backfill', $row['LastUpdateTime'] ?? null);
            $imported++;
        }

        $this->info("Imported {$imported} suppression(s) (source=backfill).");

        return self::SUCCESS;
    }

    private function mapReason(string $reason): ?string
    {
        return match (strtoupper($reason)) {
            'BOUNCE' => 'bounced',
            'COMPLAINT' => 'complained',
            default => null,
        };
    }
}

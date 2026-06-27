<?php

namespace Tests\Feature\Email;

use App\Models\EmailSuppression;
use Tests\TestCase;

class EmailSuppressionImportTest extends TestCase
{
    private ?string $file = null;

    protected function tearDown(): void
    {
        if ($this->file && file_exists($this->file)) {
            unlink($this->file);
        }

        parent::tearDown();
    }

    private function writeFixture(array $summaries): string
    {
        $this->file = tempnam(sys_get_temp_dir(), 'ses') . '.json';
        file_put_contents($this->file, json_encode(['SuppressedDestinationSummaries' => $summaries]));

        return $this->file;
    }

    private function entry(string $email, string $reason): array
    {
        return [
            'EmailAddress' => $email,
            'Reason' => $reason,
            'LastUpdateTime' => '2026-01-15T12:00:00Z',
        ];
    }

    public function test_import_upserts_by_email(): void
    {
        $path = $this->writeFixture([
            $this->entry('bounce@example.com', 'BOUNCE'),
            $this->entry('complaint@example.com', 'COMPLAINT'),
        ]);

        $this->artisan('email:import-suppressions', ['file' => $path])->assertSuccessful();

        $this->assertDatabaseHas('email_suppressions', [
            'email' => 'bounce@example.com', 'reason' => 'bounced', 'source' => 'backfill',
        ]);
        $this->assertDatabaseHas('email_suppressions', [
            'email' => 'complaint@example.com', 'reason' => 'complained', 'source' => 'backfill',
        ]);
    }

    public function test_complaint_outranks_existing_bounce(): void
    {
        EmailSuppression::suppress('person@example.com', 'bounced', 'ses');

        $path = $this->writeFixture([$this->entry('person@example.com', 'COMPLAINT')]);
        $this->artisan('email:import-suppressions', ['file' => $path])->assertSuccessful();

        $this->assertSame('complained', EmailSuppression::where('email', 'person@example.com')->value('reason'));
    }

    public function test_existing_complaint_not_downgraded_by_imported_bounce(): void
    {
        EmailSuppression::suppress('person@example.com', 'complained', 'ses');

        $path = $this->writeFixture([$this->entry('person@example.com', 'BOUNCE')]);
        $this->artisan('email:import-suppressions', ['file' => $path])->assertSuccessful();

        $this->assertSame('complained', EmailSuppression::where('email', 'person@example.com')->value('reason'));
    }

    public function test_import_is_idempotent(): void
    {
        $path = $this->writeFixture([
            $this->entry('a@example.com', 'BOUNCE'),
            $this->entry('b@example.com', 'COMPLAINT'),
        ]);

        $this->artisan('email:import-suppressions', ['file' => $path])->assertSuccessful();
        $this->artisan('email:import-suppressions', ['file' => $path])->assertSuccessful();

        $this->assertDatabaseCount('email_suppressions', 2);
    }

    public function test_missing_file_fails(): void
    {
        $this->artisan('email:import-suppressions', ['file' => '/no/such/file.json'])->assertFailed();
    }
}

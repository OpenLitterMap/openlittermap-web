<?php

namespace Tests\Feature\Migration;

use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Models\Users\User;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Tests\TestCase;

class TagReviewTest extends TestCase
{
    private const QUEUE = 'readme/audit/TagReviewQueue-2026-08.csv';

    private ?string $backup = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GenerateTagsSeeder::class);

        // The queue is a real git-tracked file — never let a test clobber it.
        if (file_exists(base_path(self::QUEUE))) {
            $this->backup = file_get_contents(base_path(self::QUEUE));
        }

        $category = Category::where('key', 'other')->firstOrFail();
        $legacy = LitterObject::firstOrCreate(['key' => 'plasticBags'], ['crowdsourced' => true]);

        $user = User::factory()->create();
        $photo = Photo::factory()->create(['verified' => 2, 'user_id' => $user->id]);

        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $legacy->id,
            'category_litter_object_id' => null,
            'quantity' => 7,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->backup !== null) {
            file_put_contents(base_path(self::QUEUE), $this->backup);
        }

        parent::tearDown();
    }

    /** @return array<int, array<string, string>> */
    private function queue(): array
    {
        $fh = fopen(base_path(self::QUEUE), 'r');
        $header = fgetcsv($fh);
        $rows = [];

        while (($line = fgetcsv($fh)) !== false) {
            if (count($line) === count($header)) {
                $rows[] = array_combine($header, $line);
            }
        }

        fclose($fh);

        return $rows;
    }

    private function row(string $key): ?array
    {
        return collect($this->queue())->firstWhere('object_key', $key);
    }

    public function test_rebuild_creates_a_row_for_every_object(): void
    {
        $this->artisan('olm:tag-review', ['--rebuild' => true])->assertExitCode(0);

        $this->assertCount(LitterObject::count(), $this->queue(), 'every tag must appear — review is exhaustive');
    }

    public function test_uncertain_objects_are_flagged_pending(): void
    {
        $this->artisan('olm:tag-review', ['--rebuild' => true])->assertExitCode(0);

        $row = $this->row('plasticBags');

        $this->assertSame('PENDING', $row['status']);
        $this->assertStringContainsString('legacy key not in TagsConfig', $row['uncertainty']);
    }

    public function test_certain_objects_need_no_review(): void
    {
        $this->artisan('olm:tag-review', ['--rebuild' => true])->assertExitCode(0);

        $clean = collect($this->queue())->firstWhere('uncertainty', '');

        $this->assertNotNull($clean, 'some objects should need no review');
        $this->assertSame('NO_REVIEW_NEEDED', $clean['status']);
    }

    public function test_marking_done_records_an_audit_trail(): void
    {
        $this->artisan('olm:tag-review', ['--rebuild' => true]);

        $this->artisan('olm:tag-review', [
            '--done' => 'plasticBags',
            '--decision' => 'Merge into plastic_bag',
            '--by' => 'tester',
        ])->assertExitCode(0);

        $row = $this->row('plasticBags');

        $this->assertSame('REVIEWED', $row['status']);
        $this->assertSame('Merge into plastic_bag', $row['decision']);
        $this->assertSame('tester', $row['decided_by']);
        $this->assertNotEmpty($row['decided_at']);
    }

    public function test_marking_done_requires_a_decision_and_an_author(): void
    {
        $this->artisan('olm:tag-review', ['--rebuild' => true]);

        $this->artisan('olm:tag-review', ['--done' => 'plasticBags'])->assertExitCode(1);
        $this->artisan('olm:tag-review', ['--done' => 'plasticBags', '--decision' => 'x'])->assertExitCode(1);

        $this->assertSame('PENDING', $this->row('plasticBags')['status'], 'unattributed decisions must not be recorded');
    }

    public function test_rebuild_preserves_recorded_decisions(): void
    {
        $this->artisan('olm:tag-review', ['--rebuild' => true]);
        $this->artisan('olm:tag-review', [
            '--done' => 'plasticBags',
            '--decision' => 'Merge into plastic_bag',
            '--by' => 'tester',
        ]);

        $this->artisan('olm:tag-review', ['--rebuild' => true])->assertExitCode(0);

        $row = $this->row('plasticBags');

        $this->assertSame('REVIEWED', $row['status'], 'a rebuild must never lose a decision');
        $this->assertSame('Merge into plastic_bag', $row['decision']);
        $this->assertSame('tester', $row['decided_by']);
    }

    public function test_reopen_clears_a_decision(): void
    {
        $this->artisan('olm:tag-review', ['--rebuild' => true]);
        $this->artisan('olm:tag-review', [
            '--done' => 'plasticBags', '--decision' => 'd', '--by' => 'tester',
        ]);

        $this->artisan('olm:tag-review', ['--reopen' => 'plasticBags'])->assertExitCode(0);

        $row = $this->row('plasticBags');

        $this->assertSame('PENDING', $row['status']);
        $this->assertSame('', $row['decision']);
        $this->assertSame('', $row['decided_by']);
    }

    public function test_unknown_key_fails(): void
    {
        $this->artisan('olm:tag-review', ['--rebuild' => true]);

        $this->artisan('olm:tag-review', ['--show' => 'nope_xyz'])->assertExitCode(1);
        $this->artisan('olm:tag-review', [
            '--done' => 'nope_xyz', '--decision' => 'd', '--by' => 'tester',
        ])->assertExitCode(1);
    }

    public function test_duplicate_candidates_are_flagged_but_never_auto_resolved(): void
    {
        $this->artisan('olm:tag-review', ['--rebuild' => true]);

        $row = $this->row('plasticBags');

        $this->assertStringContainsString('duplicate candidate: plastic_bag', $row['uncertainty']);
        $this->assertStringContainsString('CANDIDATE ONLY', $row['uncertainty']);
        $this->assertSame('PENDING', $row['status'], 'a duplicate candidate must never be auto-approved');
        $this->assertSame('', $row['decision']);
    }

    public function test_it_never_modifies_tag_data(): void
    {
        $before = PhotoTag::where('quantity', 7)->firstOrFail()->toArray();

        $this->artisan('olm:tag-review', ['--rebuild' => true]);
        $this->artisan('olm:tag-review', [
            '--done' => 'plasticBags', '--decision' => 'd', '--by' => 'tester',
        ]);

        $after = PhotoTag::find($before['id'])->toArray();

        $this->assertEquals($before, $after, 'the review queue is read-only with respect to tag data');
    }
}

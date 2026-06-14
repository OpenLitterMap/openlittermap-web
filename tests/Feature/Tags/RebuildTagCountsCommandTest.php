<?php

namespace Tests\Feature\Tags;

use App\Enums\VerificationStatus;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\LitterObjectType;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RebuildTagCountsCommandTest extends TestCase
{
    private string $outputPath;

    private string $publicPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputPath = sys_get_temp_dir().'/tag_usage_counts_'.uniqid().'.json';
        $this->publicPath = sys_get_temp_dir().'/tag_counts_public_'.uniqid().'.json';
        config([
            'tags.usage_counts_path' => $this->outputPath,
            'tags.public_counts_path' => $this->publicPath,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->outputPath, $this->publicPath] as $path) {
            if (File::exists($path)) {
                File::delete($path);
            }
        }

        parent::tearDown();
    }

    /**
     * @return array<string, int>
     */
    private function runAndReadCounts(): array
    {
        $this->artisan('olm:rebuild-tag-counts')->assertExitCode(0);

        return json_decode(File::get($this->outputPath), true)['counts'];
    }

    public function test_command_produces_correct_counts(): void
    {
        $category = Category::factory()->create();
        $object = LitterObject::factory()->create();
        $type = LitterObjectType::factory()->create();
        $photo = Photo::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            PhotoTag::create([
                'photo_id' => $photo->id,
                'category_id' => $category->id,
                'litter_object_id' => $object->id,
                'litter_object_type_id' => $type->id,
                'quantity' => 1,
            ]);
        }

        $this->artisan('olm:rebuild-tag-counts')->assertExitCode(0);

        $data = json_decode(File::get($this->outputPath), true);

        $this->assertArrayHasKey('generated_at', $data);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data['generated_at']);
        $this->assertSame('total_recorded_tags', $data['scope']);
        $this->assertSame(3, $data['counts']["{$object->id}:{$category->id}:{$type->id}"]);
    }

    public function test_soft_deleted_photos_are_excluded(): void
    {
        $category = Category::factory()->create();
        $object = LitterObject::factory()->create();

        $livePhoto = Photo::factory()->create();
        $deletedPhoto = Photo::factory()->create();

        foreach ([$livePhoto, $deletedPhoto] as $photo) {
            PhotoTag::create([
                'photo_id' => $photo->id,
                'category_id' => $category->id,
                'litter_object_id' => $object->id,
                'quantity' => 1,
            ]);
        }

        $deletedPhoto->delete();

        $counts = $this->runAndReadCounts();

        // Only the live photo's tag is counted.
        $this->assertSame(1, $counts["{$object->id}:{$category->id}:0"]);
    }

    public function test_null_type_id_uses_zero_key(): void
    {
        $category = Category::factory()->create();
        $object = LitterObject::factory()->create();
        $photo = Photo::factory()->create();

        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'litter_object_type_id' => null,
            'quantity' => 1,
        ]);

        $counts = $this->runAndReadCounts();

        $this->assertArrayHasKey("{$object->id}:{$category->id}:0", $counts);
        $this->assertSame(1, $counts["{$object->id}:{$category->id}:0"]);
    }

    public function test_extra_tag_only_rows_without_object_are_excluded(): void
    {
        $category = Category::factory()->create();
        $object = LitterObject::factory()->create();
        $photo = Photo::factory()->create();

        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'quantity' => 1,
        ]);

        // Extra-tag-only tag (brand/material/custom): null litter_object_id, excluded.
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => null,
            'litter_object_id' => null,
            'quantity' => 1,
        ]);

        $counts = $this->runAndReadCounts();

        $this->assertCount(1, $counts);
        $this->assertSame(1, $counts["{$object->id}:{$category->id}:0"]);
    }

    public function test_public_scope_filters_to_public_and_verified_on_map(): void
    {
        $category = Category::factory()->create();
        $object = LitterObject::factory()->create();

        // On the map: is_public AND verified >= 2 (ADMIN_APPROVED and BBOX_APPLIED both qualify).
        $approved = Photo::factory()->create([
            'is_public' => true,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
        ]);
        $bbox = Photo::factory()->create([
            'is_public' => true,
            'verified' => VerificationStatus::BBOX_APPLIED->value,
        ]);

        // Excluded: public but not yet on the map (verified < 2).
        $awaitingApproval = Photo::factory()->create([
            'is_public' => true,
            'verified' => VerificationStatus::VERIFIED->value,
        ]);

        // Excluded: approved but private (e.g. school or private-by-choice).
        $private = Photo::factory()->create([
            'is_public' => false,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
        ]);

        foreach ([$approved, $bbox, $awaitingApproval, $private] as $photo) {
            PhotoTag::create([
                'photo_id' => $photo->id,
                'category_id' => $category->id,
                'litter_object_id' => $object->id,
                'quantity' => 1,
            ]);
        }

        $this->artisan('olm:rebuild-tag-counts', ['--scope' => 'public'])->assertExitCode(0);

        $data = json_decode(File::get($this->publicPath), true);

        $this->assertSame('verified_public_on_map', $data['scope']);
        // Only the two on-map photos are counted; the private and not-yet-verified ones are dropped.
        $this->assertCount(1, $data['counts']);
        $this->assertSame(2, $data['counts']["{$object->id}:{$category->id}:0"]);
    }

    public function test_total_scope_leaves_public_file_untouched(): void
    {
        $category = Category::factory()->create();
        $object = LitterObject::factory()->create();
        $photo = Photo::factory()->create();

        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'quantity' => 1,
        ]);

        // Omitted scope == total: writes the internal file, never the public one.
        $this->artisan('olm:rebuild-tag-counts')->assertExitCode(0);

        $this->assertTrue(File::exists($this->outputPath));
        $this->assertSame('total_recorded_tags', json_decode(File::get($this->outputPath), true)['scope']);
        $this->assertFalse(File::exists($this->publicPath));
    }
}

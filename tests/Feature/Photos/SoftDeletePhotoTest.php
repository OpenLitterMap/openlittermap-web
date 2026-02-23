<?php

namespace Tests\Feature\Photos;

use App\Enums\VerificationStatus;
use App\Models\Location\City;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Metrics\MetricsService;
use Tests\TestCase;

class SoftDeletePhotoTest extends TestCase
{
    public function test_soft_deleted_photo_row_persists()
    {
        $photo = Photo::factory()->create();

        $photo->delete();

        $this->assertSoftDeleted('photos', ['id' => $photo->id]);
        $this->assertDatabaseHas('photos', ['id' => $photo->id]);
        $this->assertNotNull($photo->fresh()->deleted_at);
    }

    public function test_soft_deleted_photo_excluded_from_public_scope()
    {
        $photo = Photo::factory()->create([
            'is_public' => true,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
            'city_id' => City::factory(),
        ]);

        $this->assertEquals(1, Photo::public()->count());

        $photo->delete();

        $this->assertEquals(0, Photo::public()->count());
    }

    public function test_metrics_service_can_reverse_before_soft_delete()
    {
        $photo = Photo::factory()->create([
            'is_public' => true,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
            'city_id' => City::factory(),
            'processed_at' => now(),
            'processed_fp' => 'abc123',
            'processed_tags' => json_encode(['objects' => [1 => 3], 'materials' => [], 'brands' => [], 'custom_tags' => []]),
            'processed_xp' => 5,
        ]);

        app(MetricsService::class)->deletePhoto($photo);

        $photo->refresh();
        $this->assertNull($photo->processed_at);
        $this->assertNull($photo->processed_fp);
        $this->assertNull($photo->processed_tags);
        $this->assertNull($photo->processed_xp);

        $photo->delete();

        $this->assertSoftDeleted('photos', ['id' => $photo->id]);
        $this->assertEquals(0, Photo::public()->count());
    }
}

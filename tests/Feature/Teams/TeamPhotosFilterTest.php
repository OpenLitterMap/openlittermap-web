<?php

namespace Tests\Feature\Teams;

use App\Enums\VerificationStatus;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\Users\User;
use Tests\TestCase;

class TeamPhotosFilterTest extends TestCase
{
    protected User $leader;
    protected User $member;
    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->create();
        $this->member = User::factory()->create();
        $this->team = Team::factory()->create(['leader' => $this->leader->id]);
        $this->leader->teams()->attach($this->team);
        $this->member->teams()->attach($this->team);

        // Tag taxonomy
        Category::firstOrCreate(['key' => 'smoking']);
        $smokingCat = Category::where('key', 'smoking')->first();
        $butts = LitterObject::firstOrCreate(['key' => 'butts']);
        CategoryObject::firstOrCreate([
            'category_id' => $smokingCat->id,
            'litter_object_id' => $butts->id,
        ]);
    }

    public function test_filter_by_tagged_state_tagged(): void
    {
        // Tagged photo (has summary)
        Photo::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->leader->id,
            'summary' => json_encode(['totals' => ['total_tags' => 3]]),
        ]);
        // Untagged photo (no summary)
        Photo::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->leader->id,
            'summary' => null,
        ]);

        $response = $this->actingAs($this->leader)->getJson(
            '/api/teams/photos?' . http_build_query([
                'team_id' => $this->team->id,
                'tagged' => '1',
            ])
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('photos.data'));
        $this->assertNotNull($response->json('photos.data.0.summary'));
    }

    public function test_filter_by_tagged_state_untagged(): void
    {
        Photo::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->leader->id,
            'summary' => json_encode(['totals' => ['total_tags' => 3]]),
        ]);
        Photo::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->leader->id,
            'summary' => null,
        ]);

        $response = $this->actingAs($this->leader)->getJson(
            '/api/teams/photos?' . http_build_query([
                'team_id' => $this->team->id,
                'tagged' => '0',
            ])
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('photos.data'));
        $this->assertNull($response->json('photos.data.0.summary'));
    }

    public function test_filter_by_tag_name(): void
    {
        $smokingCat = Category::where('key', 'smoking')->first();
        $butts = LitterObject::where('key', 'butts')->first();
        $clo = CategoryObject::where('category_id', $smokingCat->id)
            ->where('litter_object_id', $butts->id)->first();

        $photo = Photo::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->leader->id,
        ]);
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $smokingCat->id,
            'litter_object_id' => $butts->id,
            'category_litter_object_id' => $clo->id,
            'quantity' => 1,
        ]);

        // Photo without matching tag
        Photo::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->leader->id,
        ]);

        $response = $this->actingAs($this->leader)->getJson(
            '/api/teams/photos?' . http_build_query([
                'team_id' => $this->team->id,
                'tag' => 'butts',
            ])
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('photos.data'));
        $this->assertEquals($photo->id, $response->json('photos.data.0.id'));
    }

    public function test_filter_by_date_range(): void
    {
        Photo::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->leader->id,
            'datetime' => '2025-06-15 12:00:00',
        ]);
        Photo::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->leader->id,
            'datetime' => '2024-01-01 12:00:00',
        ]);

        $response = $this->actingAs($this->leader)->getJson(
            '/api/teams/photos?' . http_build_query([
                'team_id' => $this->team->id,
                'date_from' => '2025-01-01',
                'date_to' => '2025-12-31',
            ])
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('photos.data'));
    }

    public function test_filter_by_picked_up(): void
    {
        $photo1 = Photo::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->leader->id,
        ]);
        PhotoTag::create([
            'photo_id' => $photo1->id,
            'quantity' => 1,
            'picked_up' => true,
        ]);

        $photo2 = Photo::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->leader->id,
        ]);
        PhotoTag::create([
            'photo_id' => $photo2->id,
            'quantity' => 1,
            'picked_up' => false,
        ]);

        $response = $this->actingAs($this->leader)->getJson(
            '/api/teams/photos?' . http_build_query([
                'team_id' => $this->team->id,
                'picked_up' => 'true',
            ])
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('photos.data'));
        $this->assertEquals($photo1->id, $response->json('photos.data.0.id'));
    }

    public function test_filter_by_member_id(): void
    {
        Photo::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->leader->id,
        ]);
        Photo::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->member->id,
        ]);

        $response = $this->actingAs($this->leader)->getJson(
            '/api/teams/photos?' . http_build_query([
                'team_id' => $this->team->id,
                'member_id' => $this->member->id,
            ])
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('photos.data'));
        $this->assertEquals($this->member->id, $response->json('photos.data.0.user_id'));
    }

    public function test_filter_by_photo_id_with_operators(): void
    {
        $photo1 = Photo::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->leader->id,
        ]);
        $photo2 = Photo::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->leader->id,
        ]);

        // Filter: id > first photo's id → should only return second photo
        $response = $this->actingAs($this->leader)->getJson(
            '/api/teams/photos?' . http_build_query([
                'team_id' => $this->team->id,
                'id' => $photo1->id,
                'id_operator' => '>',
            ])
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('photos.data'));
        $this->assertEquals($photo2->id, $response->json('photos.data.0.id'));
    }

    public function test_per_page_parameter(): void
    {
        Photo::factory()->count(5)->create([
            'team_id' => $this->team->id,
            'user_id' => $this->leader->id,
        ]);

        $response = $this->actingAs($this->leader)->getJson(
            '/api/teams/photos?' . http_build_query([
                'team_id' => $this->team->id,
                'per_page' => 2,
            ])
        );

        $response->assertOk();
        $this->assertCount(2, $response->json('photos.data'));
        $this->assertEquals(3, $response->json('photos.last_page'));
    }

    public function test_combined_filters(): void
    {
        // Photo that matches all filters
        $matchingPhoto = Photo::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->member->id,
            'datetime' => '2025-06-15 12:00:00',
            'summary' => json_encode(['totals' => ['total_tags' => 1]]),
        ]);

        // Photo with wrong member
        Photo::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->leader->id,
            'datetime' => '2025-06-15 12:00:00',
            'summary' => json_encode(['totals' => ['total_tags' => 1]]),
        ]);

        // Photo with wrong date
        Photo::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->member->id,
            'datetime' => '2024-01-01 12:00:00',
            'summary' => json_encode(['totals' => ['total_tags' => 1]]),
        ]);

        $response = $this->actingAs($this->leader)->getJson(
            '/api/teams/photos?' . http_build_query([
                'team_id' => $this->team->id,
                'member_id' => $this->member->id,
                'date_from' => '2025-01-01',
                'date_to' => '2025-12-31',
                'tagged' => '1',
            ])
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('photos.data'));
        $this->assertEquals($matchingPhoto->id, $response->json('photos.data.0.id'));
    }
}

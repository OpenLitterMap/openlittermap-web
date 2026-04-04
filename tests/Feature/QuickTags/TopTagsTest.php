<?php

namespace Tests\Feature\QuickTags;

use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\LitterObjectType;
use App\Models\Photo;
use App\Models\Users\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TopTagsTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_returns_empty_tags_for_user_with_no_photos(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/top-tags');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tags', []);
    }

    public function test_returns_top_tags_ordered_by_quantity(): void
    {
        $category = Category::factory()->create(['key' => 'smoking']);
        $obj1 = LitterObject::factory()->create(['key' => 'butts']);
        $obj2 = LitterObject::factory()->create(['key' => 'lighter']);

        $clo1 = CategoryObject::create(['category_id' => $category->id, 'litter_object_id' => $obj1->id]);
        $clo2 = CategoryObject::create(['category_id' => $category->id, 'litter_object_id' => $obj2->id]);

        $photo = Photo::factory()->create(['user_id' => $this->user->id]);

        DB::table('photo_tags')->insert([
            ['photo_id' => $photo->id, 'category_litter_object_id' => $clo1->id, 'category_id' => $category->id, 'litter_object_id' => $obj1->id, 'quantity' => 50, 'created_at' => now(), 'updated_at' => now()],
            ['photo_id' => $photo->id, 'category_litter_object_id' => $clo2->id, 'category_id' => $category->id, 'litter_object_id' => $obj2->id, 'quantity' => 10, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/top-tags');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'tags')
            ->assertJsonPath('tags.0.clo_id', $clo1->id)
            ->assertJsonPath('tags.0.object_key', 'butts')
            ->assertJsonPath('tags.0.category_key', 'smoking')
            ->assertJsonPath('tags.0.total', 50)
            ->assertJsonPath('tags.1.clo_id', $clo2->id)
            ->assertJsonPath('tags.1.total', 10);
    }

    public function test_groups_by_clo_and_type(): void
    {
        $category = Category::factory()->create(['key' => 'alcohol']);
        $obj = LitterObject::factory()->create(['key' => 'can']);
        $clo = CategoryObject::create(['category_id' => $category->id, 'litter_object_id' => $obj->id]);

        $beerType = LitterObjectType::factory()->create(['key' => 'beer']);
        $energyType = LitterObjectType::factory()->create(['key' => 'energy']);

        $photo = Photo::factory()->create(['user_id' => $this->user->id]);

        DB::table('photo_tags')->insert([
            ['photo_id' => $photo->id, 'category_litter_object_id' => $clo->id, 'category_id' => $category->id, 'litter_object_id' => $obj->id, 'litter_object_type_id' => $beerType->id, 'quantity' => 30, 'created_at' => now(), 'updated_at' => now()],
            ['photo_id' => $photo->id, 'category_litter_object_id' => $clo->id, 'category_id' => $category->id, 'litter_object_id' => $obj->id, 'litter_object_type_id' => $energyType->id, 'quantity' => 20, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/top-tags');

        $response->assertOk()
            ->assertJsonCount(2, 'tags')
            ->assertJsonPath('tags.0.type_key', 'beer')
            ->assertJsonPath('tags.0.total', 30)
            ->assertJsonPath('tags.1.type_key', 'energy')
            ->assertJsonPath('tags.1.total', 20);
    }

    public function test_includes_dominant_brand_over_50_percent(): void
    {
        $category = Category::factory()->create(['key' => 'softdrinks']);
        $obj = LitterObject::factory()->create(['key' => 'can']);
        $clo = CategoryObject::create(['category_id' => $category->id, 'litter_object_id' => $obj->id]);
        $type = LitterObjectType::factory()->create(['key' => 'energy']);

        $brandId = DB::table('brandslist')->insertGetId(['key' => 'redbull', 'is_custom' => false, 'created_at' => now(), 'updated_at' => now()]);

        $photo = Photo::factory()->create(['user_id' => $this->user->id]);

        // 10 total, 8 with redbull brand (80% > 50%)
        $ptId1 = DB::table('photo_tags')->insertGetId([
            'photo_id' => $photo->id, 'category_litter_object_id' => $clo->id,
            'category_id' => $category->id, 'litter_object_id' => $obj->id,
            'litter_object_type_id' => $type->id, 'quantity' => 8,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('photo_tag_extra_tags')->insert([
            'photo_tag_id' => $ptId1, 'tag_type' => 'brand', 'tag_type_id' => $brandId,
            'quantity' => 8, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('photo_tags')->insert([
            'photo_id' => $photo->id, 'category_litter_object_id' => $clo->id,
            'category_id' => $category->id, 'litter_object_id' => $obj->id,
            'litter_object_type_id' => $type->id, 'quantity' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/top-tags');

        $response->assertOk()
            ->assertJsonPath('tags.0.brand_id', $brandId)
            ->assertJsonPath('tags.0.brand_key', 'redbull')
            ->assertJsonPath('tags.0.total', 10);
    }

    public function test_excludes_brand_under_50_percent(): void
    {
        $category = Category::factory()->create(['key' => 'softdrinks']);
        $obj = LitterObject::factory()->create(['key' => 'bottle']);
        $clo = CategoryObject::create(['category_id' => $category->id, 'litter_object_id' => $obj->id]);

        $brandId = DB::table('brandslist')->insertGetId(['key' => 'cocacola', 'is_custom' => false, 'created_at' => now(), 'updated_at' => now()]);

        $photo = Photo::factory()->create(['user_id' => $this->user->id]);

        // 10 total, 4 with brand (40% < 50%)
        $ptId1 = DB::table('photo_tags')->insertGetId([
            'photo_id' => $photo->id, 'category_litter_object_id' => $clo->id,
            'category_id' => $category->id, 'litter_object_id' => $obj->id,
            'quantity' => 4, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('photo_tag_extra_tags')->insert([
            'photo_tag_id' => $ptId1, 'tag_type' => 'brand', 'tag_type_id' => $brandId,
            'quantity' => 4, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('photo_tags')->insert([
            'photo_id' => $photo->id, 'category_litter_object_id' => $clo->id,
            'category_id' => $category->id, 'litter_object_id' => $obj->id,
            'quantity' => 6, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/top-tags');

        $response->assertOk()
            ->assertJsonPath('tags.0.brand_id', null)
            ->assertJsonPath('tags.0.brand_key', null);
    }

    public function test_limit_parameter(): void
    {
        $category = Category::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $this->user->id]);

        // Create 5 distinct CLOs
        for ($i = 0; $i < 5; $i++) {
            $obj = LitterObject::factory()->create();
            $clo = CategoryObject::create(['category_id' => $category->id, 'litter_object_id' => $obj->id]);
            DB::table('photo_tags')->insert([
                'photo_id' => $photo->id, 'category_litter_object_id' => $clo->id,
                'category_id' => $category->id, 'litter_object_id' => $obj->id,
                'quantity' => 10 - $i, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/top-tags?limit=3');

        $response->assertOk()
            ->assertJsonCount(3, 'tags');
    }

    public function test_limit_capped_at_30(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/top-tags?limit=100');

        $response->assertOk()
            ->assertJsonPath('tags', []);
    }

    public function test_requires_auth(): void
    {
        $response = $this->getJson('/api/v3/user/top-tags');

        $response->assertUnauthorized();
    }

    public function test_excludes_tags_below_minimum_threshold(): void
    {
        $category = Category::factory()->create();
        $obj1 = LitterObject::factory()->create();
        $obj2 = LitterObject::factory()->create();
        $clo1 = CategoryObject::create(['category_id' => $category->id, 'litter_object_id' => $obj1->id]);
        $clo2 = CategoryObject::create(['category_id' => $category->id, 'litter_object_id' => $obj2->id]);

        $photo = Photo::factory()->create(['user_id' => $this->user->id]);

        DB::table('photo_tags')->insert([
            ['photo_id' => $photo->id, 'category_litter_object_id' => $clo1->id, 'category_id' => $category->id, 'litter_object_id' => $obj1->id, 'quantity' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['photo_id' => $photo->id, 'category_litter_object_id' => $clo2->id, 'category_id' => $category->id, 'litter_object_id' => $obj2->id, 'quantity' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/top-tags');

        $response->assertOk()
            ->assertJsonCount(1, 'tags')
            ->assertJsonPath('tags.0.total', 5);
    }

    public function test_does_not_include_other_users_tags(): void
    {
        $otherUser = User::factory()->create();
        $category = Category::factory()->create(['key' => 'smoking']);
        $obj = LitterObject::factory()->create(['key' => 'butts']);
        $clo = CategoryObject::create(['category_id' => $category->id, 'litter_object_id' => $obj->id]);

        $otherPhoto = Photo::factory()->create(['user_id' => $otherUser->id]);
        DB::table('photo_tags')->insert([
            'photo_id' => $otherPhoto->id, 'category_litter_object_id' => $clo->id,
            'category_id' => $category->id, 'litter_object_id' => $obj->id,
            'quantity' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/top-tags');

        $response->assertOk()
            ->assertJsonPath('tags', []);
    }
}

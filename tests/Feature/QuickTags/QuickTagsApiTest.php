<?php

namespace Tests\Feature\QuickTags;

use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuickTagsApiTest extends TestCase
{
    use RefreshDatabase;

    private function createClo(): int
    {
        $catId = DB::table('categories')->insertGetId(['key' => 'smoking_' . uniqid()]);
        $objId = DB::table('litter_objects')->insertGetId(['key' => 'butts_' . uniqid()]);

        return $this->getCloId($catId, $objId);
    }

    private function createType(): int
    {
        return DB::table('litter_object_types')->insertGetId([
            'key' => 'type_' . uniqid(),
            'name' => 'Test Type',
        ]);
    }

    private function createMaterial(): int
    {
        return DB::table('materials')->insertGetId(['key' => 'material_' . uniqid()]);
    }

    private function createBrand(): int
    {
        return DB::table('brandslist')->insertGetId(['key' => 'brand_' . uniqid()]);
    }

    private function makeTagPayload(int $cloId, array $overrides = []): array
    {
        return array_merge([
            'clo_id' => $cloId,
            'type_id' => null,
            'quantity' => 1,
            'picked_up' => null,
            'materials' => [],
            'brands' => [],
        ], $overrides);
    }

    public function test_guest_cannot_access_quick_tags(): void
    {
        $this->getJson('/api/v3/user/quick-tags')->assertStatus(401);
    }

    public function test_guest_cannot_update_quick_tags(): void
    {
        $this->putJson('/api/v3/user/quick-tags', ['tags' => []])->assertStatus(401);
    }

    public function test_user_gets_empty_array_when_no_quick_tags_exist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v3/user/quick-tags')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tags', []);
    }

    public function test_user_can_store_quick_tags_via_put(): void
    {
        $user = User::factory()->create();
        $clo1 = $this->createClo();
        $clo2 = $this->createClo();
        $clo3 = $this->createClo();

        $response = $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [
                    $this->makeTagPayload($clo1),
                    $this->makeTagPayload($clo2, ['quantity' => 3]),
                    $this->makeTagPayload($clo3, ['picked_up' => true]),
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'tags');

        $this->assertDatabaseCount('user_quick_tags', 3);

        // Verify sort_order
        $tags = $response->json('tags');
        $this->assertEquals(0, $tags[0]['sort_order']);
        $this->assertEquals(1, $tags[1]['sort_order']);
        $this->assertEquals(2, $tags[2]['sort_order']);
    }

    public function test_put_replaces_existing_tags(): void
    {
        $user = User::factory()->create();
        $clo1 = $this->createClo();
        $clo2 = $this->createClo();
        $clo3 = $this->createClo();

        // Store 3 tags
        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [
                    $this->makeTagPayload($clo1),
                    $this->makeTagPayload($clo2),
                    $this->makeTagPayload($clo3),
                ],
            ])->assertOk();

        // Replace with 2 different tags
        $clo4 = $this->createClo();
        $clo5 = $this->createClo();

        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [
                    $this->makeTagPayload($clo4),
                    $this->makeTagPayload($clo5),
                ],
            ])->assertOk()
            ->assertJsonCount(2, 'tags');

        // GET returns only 2 new tags
        $this->actingAs($user)
            ->getJson('/api/v3/user/quick-tags')
            ->assertOk()
            ->assertJsonCount(2, 'tags');

        $this->assertDatabaseCount('user_quick_tags', 2);
    }

    public function test_get_returns_tags_in_sort_order(): void
    {
        $user = User::factory()->create();
        $clo1 = $this->createClo();
        $clo2 = $this->createClo();
        $clo3 = $this->createClo();

        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [
                    $this->makeTagPayload($clo1),
                    $this->makeTagPayload($clo2),
                    $this->makeTagPayload($clo3),
                ],
            ])->assertOk();

        $response = $this->actingAs($user)
            ->getJson('/api/v3/user/quick-tags')
            ->assertOk();

        $tags = $response->json('tags');
        $this->assertEquals($clo1, $tags[0]['clo_id']);
        $this->assertEquals($clo2, $tags[1]['clo_id']);
        $this->assertEquals($clo3, $tags[2]['clo_id']);
    }

    public function test_put_validates_max_30_tags(): void
    {
        $user = User::factory()->create();
        $tags = [];
        for ($i = 0; $i < 31; $i++) {
            $tags[] = $this->makeTagPayload($this->createClo());
        }

        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', ['tags' => $tags])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tags');
    }

    public function test_put_validates_clo_id_exists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [$this->makeTagPayload(999999)],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tags.0.clo_id');
    }

    public function test_put_validates_type_id_exists_when_non_null(): void
    {
        $user = User::factory()->create();
        $clo = $this->createClo();

        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [$this->makeTagPayload($clo, ['type_id' => 999999])],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tags.0.type_id');
    }

    public function test_put_validates_material_ids_exist(): void
    {
        $user = User::factory()->create();
        $clo = $this->createClo();

        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [$this->makeTagPayload($clo, ['materials' => [999999]])],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tags.0.materials.0');
    }

    public function test_put_validates_brand_ids_exist(): void
    {
        $user = User::factory()->create();
        $clo = $this->createClo();

        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [$this->makeTagPayload($clo, [
                    'brands' => [['id' => 999999, 'quantity' => 1]],
                ])],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tags.0.brands.0.id');
    }

    public function test_put_validates_quantity_range(): void
    {
        $user = User::factory()->create();
        $clo = $this->createClo();

        // quantity 0
        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [$this->makeTagPayload($clo, ['quantity' => 0])],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tags.0.quantity');

        // quantity 11
        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [$this->makeTagPayload($clo, ['quantity' => 11])],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tags.0.quantity');
    }

    public function test_picked_up_stores_null_faithfully(): void
    {
        $user = User::factory()->create();
        $clo = $this->createClo();

        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [$this->makeTagPayload($clo, ['picked_up' => null])],
            ])->assertOk();

        $response = $this->actingAs($user)
            ->getJson('/api/v3/user/quick-tags')
            ->assertOk();

        $this->assertNull($response->json('tags.0.picked_up'));
    }

    public function test_materials_and_brands_json_round_trip(): void
    {
        $user = User::factory()->create();
        $clo = $this->createClo();
        $material1 = $this->createMaterial();
        $material2 = $this->createMaterial();
        $brand = $this->createBrand();

        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [$this->makeTagPayload($clo, [
                    'materials' => [$material1, $material2],
                    'brands' => [['id' => $brand, 'quantity' => 2]],
                ])],
            ])->assertOk();

        $response = $this->actingAs($user)
            ->getJson('/api/v3/user/quick-tags')
            ->assertOk();

        $tag = $response->json('tags.0');
        $this->assertEquals([$material1, $material2], $tag['materials']);
        $this->assertEquals([['id' => $brand, 'quantity' => 2]], $tag['brands']);
    }

    public function test_put_with_empty_tags_array_clears_all(): void
    {
        $user = User::factory()->create();
        $clo = $this->createClo();

        // Store a tag
        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [$this->makeTagPayload($clo)],
            ])->assertOk();

        // Clear all
        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', ['tags' => []])
            ->assertOk()
            ->assertJsonPath('tags', []);

        $this->assertDatabaseCount('user_quick_tags', 0);
    }

    public function test_user_a_cannot_see_user_b_quick_tags(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $clo = $this->createClo();

        // User A stores tags
        $this->actingAs($userA)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [$this->makeTagPayload($clo)],
            ])->assertOk();

        // User B sees empty
        $this->actingAs($userB)
            ->getJson('/api/v3/user/quick-tags')
            ->assertOk()
            ->assertJsonPath('tags', []);
    }

    public function test_deleting_user_cascades_quick_tags(): void
    {
        $user = User::factory()->create();
        $clo = $this->createClo();

        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [$this->makeTagPayload($clo)],
            ])->assertOk();

        $this->assertDatabaseCount('user_quick_tags', 1);

        $user->forceDelete();

        $this->assertDatabaseCount('user_quick_tags', 0);
    }

    public function test_type_id_is_stored_when_provided(): void
    {
        $user = User::factory()->create();
        $clo = $this->createClo();
        $typeId = $this->createType();

        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [$this->makeTagPayload($clo, ['type_id' => $typeId])],
            ])->assertOk();

        $response = $this->actingAs($user)
            ->getJson('/api/v3/user/quick-tags')
            ->assertOk();

        $this->assertEquals($typeId, $response->json('tags.0.type_id'));
    }

    public function test_response_hides_internal_fields(): void
    {
        $user = User::factory()->create();
        $clo = $this->createClo();

        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [$this->makeTagPayload($clo)],
            ])->assertOk();

        $response = $this->actingAs($user)
            ->getJson('/api/v3/user/quick-tags')
            ->assertOk();

        $tag = $response->json('tags.0');
        $this->assertArrayNotHasKey('user_id', $tag);
        $this->assertArrayNotHasKey('created_at', $tag);
        $this->assertArrayNotHasKey('updated_at', $tag);
        $this->assertArrayHasKey('id', $tag);
        $this->assertArrayHasKey('clo_id', $tag);
        $this->assertArrayHasKey('sort_order', $tag);
    }

    public function test_picked_up_false_is_distinct_from_null(): void
    {
        $user = User::factory()->create();
        $clo1 = $this->createClo();
        $clo2 = $this->createClo();

        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [
                    $this->makeTagPayload($clo1, ['picked_up' => false]),
                    $this->makeTagPayload($clo2, ['picked_up' => null]),
                ],
            ])->assertOk();

        $response = $this->actingAs($user)
            ->getJson('/api/v3/user/quick-tags')
            ->assertOk();

        $tags = $response->json('tags');
        $this->assertFalse($tags[0]['picked_up']);
        $this->assertNull($tags[1]['picked_up']);
    }

    public function test_put_rejects_missing_required_fields(): void
    {
        $user = User::factory()->create();

        // Missing clo_id
        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [['quantity' => 1, 'materials' => [], 'brands' => []]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tags.0.clo_id');

        // Missing quantity
        $clo = $this->createClo();
        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [['clo_id' => $clo, 'materials' => [], 'brands' => []]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tags.0.quantity');
    }

    public function test_put_rejects_missing_materials_and_brands_keys(): void
    {
        $user = User::factory()->create();
        $clo = $this->createClo();

        // Missing materials key
        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [['clo_id' => $clo, 'quantity' => 1, 'brands' => []]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tags.0.materials');

        // Missing brands key
        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [['clo_id' => $clo, 'quantity' => 1, 'materials' => []]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tags.0.brands');
    }

    public function test_put_validates_brand_quantity_range(): void
    {
        $user = User::factory()->create();
        $clo = $this->createClo();
        $brand = $this->createBrand();

        // Brand quantity 0
        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [$this->makeTagPayload($clo, [
                    'brands' => [['id' => $brand, 'quantity' => 0]],
                ])],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tags.0.brands.0.quantity');

        // Brand quantity 11
        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [$this->makeTagPayload($clo, [
                    'brands' => [['id' => $brand, 'quantity' => 11]],
                ])],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tags.0.brands.0.quantity');
    }

    public function test_duplicate_clo_ids_allowed_in_single_request(): void
    {
        $user = User::factory()->create();
        $clo = $this->createClo();

        // Same CLO with different quantities — valid use case (e.g. different picked_up settings)
        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [
                    $this->makeTagPayload($clo, ['quantity' => 1, 'picked_up' => true]),
                    $this->makeTagPayload($clo, ['quantity' => 3, 'picked_up' => false]),
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'tags');
    }

    public function test_deleting_clo_cascades_quick_tags(): void
    {
        $user = User::factory()->create();
        $clo = $this->createClo();

        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [$this->makeTagPayload($clo)],
            ])->assertOk();

        $this->assertDatabaseCount('user_quick_tags', 1);

        DB::table('category_litter_object')->where('id', $clo)->delete();

        $this->assertDatabaseCount('user_quick_tags', 0);
    }

    public function test_put_without_tags_key_returns_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tags');
    }

    public function test_multiple_brands_per_tag(): void
    {
        $user = User::factory()->create();
        $clo = $this->createClo();
        $brand1 = $this->createBrand();
        $brand2 = $this->createBrand();

        $this->actingAs($user)
            ->putJson('/api/v3/user/quick-tags', [
                'tags' => [$this->makeTagPayload($clo, [
                    'brands' => [
                        ['id' => $brand1, 'quantity' => 1],
                        ['id' => $brand2, 'quantity' => 3],
                    ],
                ])],
            ])->assertOk();

        $response = $this->actingAs($user)
            ->getJson('/api/v3/user/quick-tags')
            ->assertOk();

        $brands = $response->json('tags.0.brands');
        $this->assertCount(2, $brands);
        $this->assertEquals($brand1, $brands[0]['id']);
        $this->assertEquals(3, $brands[1]['quantity']);
    }
}

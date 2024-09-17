<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Tests\Feature\HasPhotoUploads;
use App\Models\Photo;
use App\Models\User\User;
use App\Actions\LogAdminVerificationAction;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Litter\Categories\Alcohol;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class UpdateTagsDeletePhotoTest extends TestCase
{
    use HasPhotoUploads;

    protected User $user;
    protected User $admin;
    protected Photo $photo;
    private array $imageAndAttributes;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('bbox');

        $this->setImagePath();

        /** @var User $admin */
        $this->admin = User::factory()
            ->create(['verification_required' => false])
            ->assignRole(Role::create(['name' => 'admin']));

        $this->user = User::factory()->create(['verification_required' => true]);

        // User uploads and tags an image -------------------
        $this->actingAs($this->user);

        $this->imageAndAttributes = $this->getImageAndAttributes();

        $this->post('/submit', ['file' => $this->imageAndAttributes['file']]);

        $this->photo = $this->user->fresh()->photos->last();

        $this->post('/add-tags', [
            'photo_id' => $this->photo->id,
            'picked_up' => false,
            'tags' => [
                'smoking' => [
                    'butts' => 3
                ]
            ],
            'custom_tags' => ['test']
        ]);
    }

    public static function provider(): array
    {
        return [
            ['route' => '/admin/contentsupdatedelete', 'deletesPhoto' => true, 'tagsKey' => 'categories'],
            ['route' => '/admin/update-tags', 'deletesPhoto' => false, 'tagsKey' => 'tags']
        ];
    }

    /**
     * @dataProvider provider
     */
    public function test_an_admin_can_update_tags(
        $route, $deletesPhoto, $tagsKey
    )
    {
        // Admin updates the tags -------------------
        $this->actingAs($this->admin);

        Storage::disk('s3')->assertExists($this->imageAndAttributes['filepath']);
        Storage::disk('bbox')->assertExists($this->imageAndAttributes['filepath']);

        $smokingId = $this->photo->smoking_id;

        $this->post($route, [
            'photoId' => $this->photo->id,
            $tagsKey => [
                'alcohol' => [
                    'beerBottle' => 10
                ]
            ],
            'custom_tags' => ['new-test']
        ])->assertOk();

        // Assert tags are stored correctly ------------
        $this->photo->refresh();

        $this->assertNull($this->photo->smoking_id);
        $this->assertDatabaseMissing('smoking', ['id' => $smokingId]);

        $this->assertNotNull($this->photo->alcohol_id);
        $this->assertInstanceOf(Alcohol::class, $this->photo->alcohol);
        $this->assertEquals(10, $this->photo->alcohol->beerBottle);
        $this->assertEquals('new-test', $this->photo->customTags->first()->tag);

        if ($deletesPhoto) {
            // Assert photo is deleted
            Storage::disk('s3')->assertMissing($this->imageAndAttributes['filepath']);
            Storage::disk('bbox')->assertMissing($this->imageAndAttributes['filepath']);

            $this->assertEquals('/assets/verified.jpg', $this->photo->filename);
        }
    }

    /**
     * @dataProvider provider
     */
    public function test_user_and_photo_info_are_updated_when_an_admin_updates_tags_of_a_photo(
        $route,
        $deletesPhoto,
        $tagsKey
    )
    {
        // Admin updates the tags -------------------
        $this->actingAs($this->admin);

        $this->assertEquals(0, $this->admin->xp_redis);

        $this->post($route, [
            'photoId' => $this->photo->id,
            $tagsKey => [
                'alcohol' => [
                    'beerBottle' => 10
                ]
            ],
            'custom_tags' => ['new-test']
        ])->assertOk();

        // Assert user and photo info are stored correctly ------------
        $this->user->refresh();
        $this->photo->refresh();

        // Admin is rewarded with 1 XP for the effort
        // + 2xp for deleting tag and custom tag
        // + 2xp for adding new tag + custom tag
        $this->assertEquals(5, $this->admin->xp_redis);
        // 1 xp from uploading, xp from other tags is removed
        $this->assertEquals(1, $this->user->xp_redis);
        $this->assertEquals(10, $this->photo->total_litter);
        $this->assertFalse($this->photo->picked_up);
        $this->assertEquals(1, $this->photo->verification);
        $this->assertEquals(2, $this->photo->verified);
    }

    /**
     * @dataProvider provider
     */
    public function test_it_fires_tags_verified_by_admin_event_when_an_admin_updates_tags_of_a_photo(
        $route, $deletesPhoto, $tagsKey
    )
    {
        Event::fake(TagsVerifiedByAdmin::class);

        // Admin updates the tags -------------------
        $this->actingAs($this->admin);

        $this->post($route, [
            'photoId' => $this->photo->id,
            $tagsKey => [
                'alcohol' => [
                    'beerBottle' => 10
                ]
            ]
        ]);

        // Assert event is fired ------------
        Event::assertDispatched(
            TagsVerifiedByAdmin::class,
            function (TagsVerifiedByAdmin $e) {
                return $e->photo_id === $this->photo->id;
            }
        );
    }

    /**
     * @dataProvider provider
     */
    public function test_it_logs_the_admin_action(
        $route, $deletesPhoto, $tagsKey
    )
    {
        $spy = $this->spy(LogAdminVerificationAction::class);

        $this->actingAs($this->admin)
            ->post($route, [
                'photoId' => $this->photo->id,
                $tagsKey => ['alcohol' => ['beerBottle' => 10]]
            ]);

        $spy->shouldHaveReceived('run');
    }

    /**
     * @dataProvider provider
     */
    public function test_leaderboards_are_updated_when_an_admin_updates_tags_of_a_photo(
        $route, $deletesPhoto, $tagsKey
    )
    {
        // User has already uploaded and tagged the image, so their xp is 5
        Redis::zrem('xp.users', $this->admin->id);
        Redis::zadd("xp.users", 5, $this->user->id);
        Redis::zadd("xp.country.{$this->photo->country_id}", 5, $this->user->id);
        Redis::zadd("xp.country.{$this->photo->country_id}.state.{$this->photo->state_id}", 5, $this->user->id);
        Redis::zadd("xp.country.{$this->photo->country_id}.state.{$this->photo->state_id}.city.{$this->photo->city_id}", 5, $this->user->id);

        $this->assertEquals(0, $this->admin->xp_redis);

        // Admin updates the tags -------------------
        $this->actingAs($this->admin);

        $this->post($route, [
            'photoId' => $this->photo->id,
            $tagsKey => ['alcohol' => ['beerBottle' => 10]],
            'custom_tags' => ['new-test']
        ]);

        // Assert leaderboards are updated ------------
        $this->assertEquals(5, $this->admin->xp_redis);
        $this->assertEquals(1, Redis::zscore("xp.users", $this->user->id));
        $this->assertEquals(1, Redis::zscore("xp.country.{$this->photo->country_id}", $this->user->id));
        $this->assertEquals(1, Redis::zscore("xp.country.{$this->photo->country_id}.state.{$this->photo->state_id}", $this->user->id));
        $this->assertEquals(1, Redis::zscore("xp.country.{$this->photo->country_id}.state.{$this->photo->state_id}.city.{$this->photo->city_id}", $this->user->id));
    }
}

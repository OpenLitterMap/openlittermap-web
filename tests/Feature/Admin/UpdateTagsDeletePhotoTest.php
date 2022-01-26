<?php

namespace Tests\Feature\Admin;

use App\Events\TagsVerifiedByAdmin;
use App\Models\Litter\Categories\Alcohol;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class UpdateTagsDeletePhotoTest extends TestCase
{
    use HasPhotoUploads;

    /** @var User */
    protected $admin;
    /** @var User */
    protected $user;
    /** @var Photo */
    protected $photo;
    /** @var array */
    private $imageAndAttributes;

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
            'presence' => true,
            'tags' => [
                'smoking' => [
                    'butts' => 3
                ]
            ]
        ]);
    }

    public function provider(): array
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
            ]
        ])->assertOk();

        // Assert tags are stored correctly ------------
        $this->photo->refresh();

        $this->assertNull($this->photo->smoking_id);
        $this->assertDatabaseMissing('smoking', ['id' => $smokingId]);

        $this->assertNotNull($this->photo->alcohol_id);
        $this->assertInstanceOf(Alcohol::class, $this->photo->alcohol);
        $this->assertEquals(10, $this->photo->alcohol->beerBottle);

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
        $route, $deletesPhoto, $tagsKey
    )
    {
        // Admin updates the tags -------------------
        $this->actingAs($this->admin);

        $this->post($route, [
            'photoId' => $this->photo->id,
            $tagsKey => [
                'alcohol' => [
                    'beerBottle' => 10
                ]
            ]
        ])->assertOk();

        // Assert user and photo info are stored correctly ------------
        $this->user->refresh();
        $this->photo->refresh();

        $this->assertEquals(11, $this->user->xp); // 1 xp from uploading, + 10xp from alcohol

        $this->assertEquals(10, $this->photo->total_litter);
        $this->assertEquals(0, $this->photo->remaining);
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
    public function test_leaderboards_are_updated_when_an_admin_updates_tags_of_a_photo(
        $route, $deletesPhoto, $tagsKey
    )
    {
        // User has already uploaded and tagged the image, so their xp is 4
        Redis::zadd("xp.users", 4, $this->user->id);
        Redis::zadd("xp.country.{$this->photo->country_id}", 4, $this->user->id);
        Redis::zadd("xp.country.{$this->photo->country_id}.state.{$this->photo->state_id}", 4, $this->user->id);
        Redis::zadd("xp.country.{$this->photo->country_id}.state.{$this->photo->state_id}.city.{$this->photo->city_id}", 4, $this->user->id);
        // Admin updates the tags -------------------
        $this->actingAs($this->admin);

        $this->post($route, [
            'photoId' => $this->photo->id,
            $tagsKey => ['alcohol' => ['beerBottle' => 10]]
        ]);

        // Assert leaderboards are updated ------------
        $this->assertEquals(11, Redis::zscore("xp.users", $this->user->id));
        $this->assertEquals(11, Redis::zscore("xp.country.{$this->photo->country_id}", $this->user->id));
        $this->assertEquals(11, Redis::zscore("xp.country.{$this->photo->country_id}.state.{$this->photo->state_id}", $this->user->id));
        $this->assertEquals(11, Redis::zscore("xp.country.{$this->photo->country_id}.state.{$this->photo->state_id}.city.{$this->photo->city_id}", $this->user->id));
    }
}

<?php

namespace Tests\Unit\Models;

use App\Models\AI\Annotation;
use App\Models\Litter\Categories\Alcohol;
use App\Models\Litter\Categories\Art;
use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Categories\Coastal;
use App\Models\Litter\Categories\Coffee;
use App\Models\Litter\Categories\Dogshit;
use App\Models\Litter\Categories\Dumping;
use App\Models\Litter\Categories\Food;
use App\Models\Litter\Categories\Industrial;
use App\Models\Litter\Categories\Other;
use App\Models\Litter\Categories\Sanitary;
use App\Models\Litter\Categories\Smoking;
use App\Models\Litter\Categories\SoftDrinks;
use App\Models\Litter\Categories\TrashDog;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhotoTest extends TestCase
{
    public function test_photos_database_has_expected_columns()
    {
        $this->assertTrue(
            Schema::hasColumns('photos', [
                'id', 'user_id', 'filename', 'model', 'datetime', 'lat', 'lon', 'verification', 'verified', 'remaining', 'result_string',
                'total_litter', 'display_name', 'location', 'road', 'suburb', 'city', 'county', 'state_district',
                'country', 'country_code', 'city_id', 'state_id', 'country_id', 'smoking_id', 'alcohol_id', 'coffee_id',
                'food_id', 'softdrinks_id', 'dumping_id', 'sanitary_id', 'industrial_id', 'other_id', 'coastal_id',
                'art_id', 'brands_id', 'trashdog_id', 'dogshit_id', 'platform', 'bounding_box', 'geohash', 'team_id',
                'bbox_skipped', 'skipped_by', 'bbox_assigned_to', 'wrong_tags', 'wrong_tags_by',
                'bbox_verification_assigned_to', 'five_hundred_square_filepath'
            ])
        );
    }

    public function test_a_photo_has_proper_casts()
    {
        $casts = Photo::factory()->create()->getCasts();

        $this->assertContains('datetime', $casts);
    }

    public function test_a_photo_has_selected_attribute()
    {
        $photo = Photo::factory()->create();

        $this->assertFalse($photo->selected);
    }

    public function test_a_photo_has_picked_up_attribute()
    {
        $photo = Photo::factory()->create();

        $this->assertEquals(!$photo->remaining, $photo->picked_up);
    }

    public function test_a_photo_has_many_boxes()
    {
        $photo = Photo::factory()->create();

        $annotation = Annotation::factory()->create([
            'photo_id' => $photo->id
        ]);

        $this->assertInstanceOf(Collection::class, $photo->boxes);
        $this->assertCount(1, $photo->boxes);
        $this->assertTrue($annotation->is($photo->boxes->first()));
    }

    public function test_photos_have_categories()
    {
        $this->assertNotEmpty(Photo::categories());
        $this->assertEqualsCanonicalizing(
            [
                'smoking',
                'food',
                'coffee',
                'alcohol',
                'softdrinks',
                'sanitary',
                'coastal',
                'dumping',
                'industrial',
                'brands',
                'dogshit',
                'art',
                'material',
                'other'
            ],
            Photo::categories()
        );
    }

    public function test_photos_have_brands()
    {
        $this->assertNotEmpty(Photo::getBrands());
        $this->assertEquals(Brand::types(), Photo::getBrands());
    }

    public function test_a_photo_has_a_translated_string_of_its_categories()
    {
        $smoking = Smoking::factory()->create();
        $food = Food::factory()->create();
        $photo = Photo::factory()->create([
            'smoking_id' => $smoking->id,
            'food_id' => $food->id
        ]);

        $photo->translate();

        $this->assertSame(
            $smoking->translate() . $food->translate(),
            $photo->result_string
        );
    }

    public function test_a_photo_has_a_count_of_total_litter_in_it()
    {
        $smoking = Smoking::factory(['butts' => 1])->create();
        $brands = Brand::factory(['walkers' => 1])->create();
        $photo = Photo::factory()->create([
            'smoking_id' => $smoking->id,
            'brands_id' => $brands->id
        ]);

        $photo->total();

        // Brands are not calculated
        $this->assertEquals($smoking->total(), $photo->total_litter);
    }

    public function test_a_photo_removes_empty_tags_from_categories()
    {
        $smoking = Smoking::factory([
            'butts' => 1, 'lighters' => null
        ])->create();
        $brands = Brand::factory([
            'walkers' => 1, 'amazon' => null
        ])->create();
        $photo = Photo::factory()->create([
            'smoking_id' => $smoking->id,
            'brands_id' => $brands->id
        ]);

        // As a sanity check, we first test that
        // the current state is as we expect it to be
        $this->assertSame(1, $photo->smoking->butts);
        $this->assertSame(1, $photo->brands->walkers);

        $this->assertArrayHasKey(
            'lighters', $photo->smoking->getAttributes()
        );
        $this->assertArrayHasKey(
            'amazon', $photo->brands->getAttributes()
        );

        $photo->tags();

        $this->assertSame(1, $photo->smoking->butts);
        $this->assertSame(1, $photo->brands->walkers);

        $this->assertArrayNotHasKey(
            'lighters', $photo->smoking->getAttributes()
        );
        $this->assertArrayNotHasKey(
            'amazon', $photo->brands->getAttributes()
        );
    }

    public function test_a_photo_has_a_user()
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id
        ]);

        $this->assertInstanceOf(User::class, $photo->user);
        $this->assertTrue($user->is($photo->user));
    }

    public function test_a_photo_has_a_smoking_relationship()
    {
        $smoking = Smoking::factory()->create();
        $photo = Photo::factory()->create([
            'smoking_id' => $smoking->id
        ]);

        $this->assertInstanceOf(Smoking::class, $photo->smoking);
        $this->assertTrue($smoking->is($photo->smoking));
    }

    public function test_a_photo_has_a_food_relationship()
    {
        $food = Food::factory()->create();
        $photo = Photo::factory()->create([
            'food_id' => $food->id
        ]);

        $this->assertInstanceOf(Food::class, $photo->food);
        $this->assertTrue($food->is($photo->food));
    }

    public function test_a_photo_has_a_coffee_relationship()
    {
        $coffee = Coffee::factory()->create();
        $photo = Photo::factory()->create([
            'coffee_id' => $coffee->id
        ]);

        $this->assertInstanceOf(Coffee::class, $photo->coffee);
        $this->assertTrue($coffee->is($photo->coffee));
    }

    public function test_a_photo_has_a_softdrinks_relationship()
    {
        $softdrinks = SoftDrinks::factory()->create();
        $photo = Photo::factory()->create([
            'softdrinks_id' => $softdrinks->id
        ]);

        $this->assertInstanceOf(SoftDrinks::class, $photo->softdrinks);
        $this->assertTrue($softdrinks->is($photo->softdrinks));
    }

    public function test_a_photo_has_an_alcohol_relationship()
    {
        $alcohol = Alcohol::factory()->create();
        $photo = Photo::factory()->create([
            'alcohol_id' => $alcohol->id
        ]);

        $this->assertInstanceOf(Alcohol::class, $photo->alcohol);
        $this->assertTrue($alcohol->is($photo->alcohol));
    }

    public function test_a_photo_has_a_sanitary_relationship()
    {
        $sanitary = Sanitary::factory()->create();
        $photo = Photo::factory()->create([
            'sanitary_id' => $sanitary->id
        ]);

        $this->assertInstanceOf(Sanitary::class, $photo->sanitary);
        $this->assertTrue($sanitary->is($photo->sanitary));
    }

    public function test_a_photo_has_a_dumping_relationship()
    {
        $dumping = Dumping::factory()->create();
        $photo = Photo::factory()->create([
            'dumping_id' => $dumping->id
        ]);

        $this->assertInstanceOf(Dumping::class, $photo->dumping);
        $this->assertTrue($dumping->is($photo->dumping));
    }

    public function test_a_photo_has_an_other_relationship()
    {
        $other = Other::factory()->create();
        $photo = Photo::factory()->create([
            'other_id' => $other->id
        ]);

        $this->assertInstanceOf(Other::class, $photo->other);
        $this->assertTrue($other->is($photo->other));
    }

    public function test_a_photo_has_an_industrial_relationship()
    {
        $industrial = Industrial::factory()->create();
        $photo = Photo::factory()->create([
            'industrial_id' => $industrial->id
        ]);

        $this->assertInstanceOf(Industrial::class, $photo->industrial);
        $this->assertTrue($industrial->is($photo->industrial));
    }

    public function test_a_photo_has_a_coastal_relationship()
    {
        $coastal = Coastal::factory()->create();
        $photo = Photo::factory()->create([
            'coastal_id' => $coastal->id
        ]);

        $this->assertInstanceOf(Coastal::class, $photo->coastal);
        $this->assertTrue($coastal->is($photo->coastal));
    }

    public function test_a_photo_has_an_art_relationship()
    {
        $art = Art::factory()->create();
        $photo = Photo::factory()->create([
            'art_id' => $art->id
        ]);

        $this->assertInstanceOf(Art::class, $photo->art);
        $this->assertTrue($art->is($photo->art));
    }

    public function test_a_photo_has_a_brands_relationship()
    {
        $brands = Brand::factory()->create();
        $photo = Photo::factory()->create([
            'brands_id' => $brands->id
        ]);

        $this->assertInstanceOf(Brand::class, $photo->brands);
        $this->assertTrue($brands->is($photo->brands));
    }

    public function test_a_photo_has_a_trashdog_relationship()
    {
        $trashdog = TrashDog::factory()->create();
        $photo = Photo::factory()->create([
            'trashdog_id' => $trashdog->id
        ]);

        $this->assertInstanceOf(TrashDog::class, $photo->trashdog);
        $this->assertTrue($trashdog->is($photo->trashdog));
    }

    public function test_a_photo_has_a_dogshit_relationship()
    {
        $dogshit = Dogshit::factory()->create();
        $photo = Photo::factory()->create([
            'dogshit_id' => $dogshit->id
        ]);

        $this->assertInstanceOf(Dogshit::class, $photo->dogshit);
        $this->assertTrue($dogshit->is($photo->dogshit));
    }
}

<?php

namespace Tests\Unit\Models;

use App\Models\AI\Annotation;
use App\Models\Litter\Categories\Food;
use App\Models\Litter\Categories\Smoking;
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
            ]));
    }

    public function test_a_photo_has_selected_attribute()
    {
        $photo = Photo::factory()->create();

        $this->assertFalse($photo->selected);
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

    public function test_a_photo_has_categories()
    {
        $photo = Photo::factory()->create();

        $this->assertIsArray($photo->categories());
        $this->assertNotEmpty($photo->categories());
    }

    public function test_a_photo_has_an_owner()
    {
        $owner = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $owner->id
        ]);

        $this->assertInstanceOf(User::class, $photo->owner);
        $this->assertTrue($owner->is($photo->owner));
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

}

<?php

namespace Database\Seeders;

use App\Actions\Tags\AddTagsToPhotoActionNew;
use App\Events\ImageUploaded;
use App\Events\Photo\IncrementPhotoMonth;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\Materials;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class GenerateDataForLocations extends Seeder
{
    private AddTagsToPhotoActionNew $addTagsAction;

    public function __construct(AddTagsToPhotoActionNew $addTagsAction)
    {
        $this->addTagsAction = $addTagsAction;
    }

    public function run(): void
    {
        $countries = Country::all();

        foreach ($countries as $country) {
            foreach ($country->states as $state) {
                foreach ($state->cities as $city) {
                    $this->generateDataFor($city);
                }
                $this->generateDataFor($state);
            }
            $this->generateDataFor($country);
        }
    }

    protected function generateDataFor(Model $location): void
    {
        echo "Generating data for {$location->name}... " . get_class($location) . "\n";

        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $photo = $this->createPhoto($location, $faker);

            // Update this
            $randomTags = $this->generateRandomTags($faker);

            $this->addTagsAction->run($photo->user_id, $photo->id, $randomTags);
        }
    }

    protected function createPhoto(Model $location, $faker): Photo
    {
        $userId = User::inRandomOrder()->first()->id;

        // Determine location details based on the type of $location.
        $country = null;
        $state   = null;
        $city    = null;

        if ($location instanceof City) {
            $city    = $location;
            $state   = $city->state;
            $country = $state->country;
        } elseif ($location instanceof State) {
            $state   = $location;
            $country = $state->country;
        } elseif ($location instanceof Country) {
            $country = $location;
        }

        $date = $faker->dateTimeBetween('-3 years', 'now')->format('Y-m-d H:i:s');

        // need lat + lon for each location
        $photo = Photo::create([
            'user_id'        => $userId,
            'filename'       => 'images/butts.png',
            'model'          => "iPhone 12",
            'datetime'       => $date,
            'verified'       => 2,
            'verification'   => 1,
            'remaining'      => false,
            'lat'            => $faker->latitude,
            'lon'            => $faker->longitude,
            'display_name'   => $faker->address,
            'location'       => $faker->word,
            'road'           => $faker->streetName,
            'suburb'         => $faker->citySuffix,
            'country_id'     => $country ? $country->id : null,
            'state_id'       => $state ? $state->id : null,
            'city_id'        => $city ? $city->id : null,
            'country_code'   => $country ? $country->shortcode : null,
            'country'        => $country ? $country->country : null,
            'state_district' => $state ? $state->state : null,
            'city'           => $city ? $city->city : null,
        ]);

        event(new ImageUploaded(
            $userId,
            $photo,
            $country,
            $state,
            $city,
        ));

        event(new IncrementPhotoMonth(
            $country?->id,
            $state?->id,
            $city?->id,
            $date
        ));

        return $photo;
    }

    protected function generateRandomTags($faker): array
    {
        // Get a random Category.
        $category = Category::inRandomOrder()->first();

        if (!$category) {
            return [];
        }

        // Get a random litter object belonging to this category.
        $object = $category->litterObjects()->inRandomOrder()->first();
        if (!$object) {
            return [];
        }

        $tag = [
            'category'   => ['id' => $category->id],
            'object'     => ['id' => $object->id],
            'quantity'   => $faker->numberBetween(1, 5),
            'picked_up'  => $faker->boolean()
        ];

        // With some probability, add a custom tag.
        if ($faker->boolean(20)) {
            $tag['custom'] = $faker->word;
        }

        // With some probability, add extra material data.
        if ($faker->boolean(50)) {
            $material = Materials::inRandomOrder()->first();
            if ($material) {
                $tag['materials'] = [['id' => $material->id, 'quantity' => $faker->numberBetween(1, 3)]];
            }
        }

        // With some probability, add additional custom tags.
        if ($faker->boolean(30)) {
            $tag['custom_tags'] = [['key' => $faker->word, 'quantity' => $faker->numberBetween(1, 2)]];
        }

        // With some probability, add brand information.
        if ($faker->boolean(30)) {
            $brand = BrandList::inRandomOrder()->first();
            if ($brand) {
                $tag['brands'] = [['id' => $brand->id, 'quantity' => $faker->numberBetween(1, 3), 'key' => $brand->key]];
            }
        }

        return [$tag];
    }
}

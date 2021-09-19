<?php

namespace Tests\Unit\Exports;


use App\Exports\CreateCSVExport;
use App\Models\Photo;
use Tests\TestCase;

class CreateCSVExportTest extends TestCase
{
    public function test_it_has_correct_headings_for_all_categories_and_tags()
    {
        $expected = [
            'id', 'verification', 'phone', 'datetime', 'lat', 'lon', 'picked up', 'address', 'total_litter'
        ];

        foreach (Photo::categories() as $category) {
            $photo = Photo::factory()->make();
            $types = $photo->$category()->make()->types();

            $expected[] = strtoupper($category);
            $expected = array_merge($expected, $types);
        }

        // We make this assertion to be sure that
        // the exporter does not persist extra models
        $this->assertDatabaseCount('photos', 0);

        $export = new CreateCSVExport('null', 1, null, null);

        $this->assertEquals($expected, $export->headings());

        $this->assertDatabaseCount('photos', 0);
    }

    public function test_it_has_correct_mappings_for_all_categories_and_tags()
    {
        /** @var Photo $photo */
        $photo = Photo::factory()->create([
            'verified' => 1,
            'model' => 'Redmi Note 8 pro',
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'remaining' => true,
            'display_name' => '12345 Street',
            'total_litter' => 500
        ]);

        $expected = [
            $photo->id,
            $photo->verified,
            $photo->model,
            $photo->datetime,
            $photo->lat,
            $photo->lon,
            $photo->remaining ? 'No' : 'Yes',
            $photo->display_name,
            $photo->total_litter,
        ];

        foreach (Photo::categories() as $category) {
            $model = $this->createCategoryWithTags($photo, $category);

            // The category name has a null value
            $expected[] = null;
            // The category tags
            foreach ($model->types() as $type) {
                $expected[] = $model->$type;
            }
        }

        // We make this assertion to be sure that
        // the exporter does not persist extra models
        $this->assertDatabaseCount('photos', 1);

        $export = new CreateCSVExport('null', 1, null, null);

        $this->assertEquals($expected, $export->map($photo->fresh()));

        $this->assertDatabaseCount('photos', 1);
    }

    public function test_it_maps_to_null_values_for_all_missing_categories()
    {
        /** @var Photo $photo */
        $photo = Photo::factory()->create([
            'verified' => 1,
            'model' => 'Redmi Note 8 pro',
            'datetime' => now()->toDateTimeString(),
            'lat' => 42.0,
            'lon' => 42.0,
            'remaining' => true,
            'display_name' => '12345 Street',
            'total_litter' => 500
        ]);

        $expected = [
            $photo->id,
            $photo->verified,
            $photo->model,
            $photo->datetime,
            $photo->lat,
            $photo->lon,
            $photo->remaining ? 'No' : 'Yes',
            $photo->display_name,
            $photo->total_litter,
        ];

        foreach (Photo::categories() as $category) {
            $model = $photo->$category()->make();

            // The category name has a null value
            $expected[] = null;
            // The category tags also have null values
            foreach ($model->types() as $type) {
                $expected[] = $model->$type;
            }
        }

        $export = new CreateCSVExport('null', 1, null, null);

        $this->assertEquals($expected, $export->map($photo->fresh()));
    }

    /**
     * Creates a category
     * sets all it's tags to a unique number from 1 to n
     * updates the photo's category_id
     *
     * @param Photo $photo
     * @param string $category
     * @return mixed
     */
    protected function createCategoryWithTags(Photo $photo, string $category)
    {
        static $counter = 1;

        $types = $photo->$category()->make()->types();

        $withTypes = [];
        foreach ($types as $type) {
            $withTypes[$type] = $counter++;
        }

        $model = $photo->$category()->create($withTypes);

        $photo->update([$category . "_id" => $model->id]);

        return $model;
    }

}

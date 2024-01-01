<?php

namespace Tests\Unit\Models\Litter;


use App\Models\Photo;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LitterCategoryTest extends TestCase
{
    public function getCategories(): array
    {
        return array_map(function ($category) {
            return [$category];
        }, Photo::categories());
    }

    /**
     * @dataProvider getCategories
     * @param $category
     */
    public function test_categories_have_identical_types_as_their_schema($category)
    {
        $photo = Photo::factory()->make();
        $model = $photo->$category()->make([]);
        $types = $model->types();

        $columnListing = $this->deleteArrValues(
            Schema::getColumnListing($model->getTable()),
            ['id', 'created_at', 'updated_at']
        );

        $this->assertEqualsCanonicalizing($types, $columnListing);
    }

    /**
     * @dataProvider getCategories
     * @param $category
     */
    public function test_categories_have_no_guarded_properties($category)
    {
        $photo = Photo::factory()->make();
        $model = $photo->$category()->make([]);

        $this->assertEmpty($model->getGuarded());
    }

    /**
     * @dataProvider getCategories
     * @param $category
     */
    public function test_a_photo_has_a_translated_string_of_its_categories($category)
    {
        $photo = Photo::factory()->make();
        $types = $photo->$category()->make()->types();

        $withTypes = [];
        foreach ($types as $type) {
            $withTypes[$type] = 1;
        }

        $model = $photo->$category()->create($withTypes);

        $expected = '';
        foreach ($types as $type) {
            $className = $model->getTable() == 'arts' ? 'art' : $model->getTable();
            $expected .= $className . '.' . $type . ' ' . $model->$type . ',';
        }

        $this->assertSame($expected, $model->translate());
    }

    /**
     * Simply removes a subset of values from an array
     */
    private function deleteArrValues(array $arr, array $remove): array
    {
        return array_filter($arr, function ($e) use ($remove) {
            return !in_array($e, $remove);
        });
    }

}

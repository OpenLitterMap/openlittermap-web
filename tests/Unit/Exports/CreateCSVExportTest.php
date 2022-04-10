<?php

namespace Tests\Unit\Exports;


use App\Exports\CreateCSVExport;
use App\Models\Category;
use App\Models\Photo;
use App\Models\Tag;
use Tests\TestCase;

class CreateCSVExportTest extends TestCase
{
    public function test_it_has_correct_headings_for_all_categories_and_tags()
    {
        Tag::factory(5)->create();
        $expected = ['id', 'verification', 'phone', 'datetime', 'lat', 'lon', 'picked up', 'address', 'total_litter'];
        foreach (Category::with('tags')->get() as $category) {
            $expected[] = strtoupper($category->name);
            $expected = array_merge($expected, $category->tags->pluck('name')->toArray());
        }
        $expected = array_merge($expected, ['custom_tag_1', 'custom_tag_2', 'custom_tag_3']);

        $export = new CreateCSVExport('null', 1, null, null);

        $this->assertEquals($expected, $export->headings());
    }

    public function test_it_has_correct_mappings_for_all_categories_and_tags()
    {
        $tags = Tag::factory(5)->create();
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
        $photo->customTags()->createMany([['tag' => 'tag 1'], ['tag' => 'tag 2'], ['tag' => 'tag 3']]);
        $photo->tags()->attach(
            $tags->take(3)
                ->keyBy('id')
                ->mapWithKeys(function ($tag) {
                    return [
                        'quantity' => $tag->id // this is just to have a different count for each tag
                    ];
                })
        );
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
        foreach (Category::with('tags')->get() as $category) {
            // The category name has a null value
            $expected[] = null;
            // The category tags
            $expected = array_merge($expected, $category->tags->map(function ($tag) use ($photo) {
                return $photo->tags->where('pivot.tag_id', $tag->id)->first()->quantity ?? null;
            })->toArray());
        }
        $expected = array_merge($expected, ['tag 1', 'tag 2', 'tag 3']);

        $export = new CreateCSVExport('null', 1, null, null);

        $this->assertEquals($expected, $export->map($photo->fresh()->load('tags')));
    }
}

<?php

namespace Database\Factories\AI;

use App\Models\AI\Annotation;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnnotationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Annotation::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'photo_id' => Photo::factory()->create(),
            'category' => $this->faker->name,
            'category_id' => $this->faker->randomDigit,
            'supercategory_id' => $this->faker->randomDigit,
            'tag' => $this->faker->name,
            'tag_id' => $this->faker->randomDigit,
            'brand' => $this->faker->name,
            'brand_id' => $this->faker->randomDigit,
            'bbox' => $this->faker->sentence,
            'segmentation' => $this->faker->sentence,
            'is_crowd' => 0,
            'area' => 1,
            'added_by' => User::factory()->create(),
            'verified_by' => User::factory()->create(),
        ];
    }
}

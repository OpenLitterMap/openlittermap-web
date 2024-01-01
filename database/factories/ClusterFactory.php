<?php

namespace Database\Factories;

use App\Models\Cluster;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClusterFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Cluster::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $pointCount = $this->faker->randomDigitNotNull;
        return [
            'lat' => $this->faker->latitude,
            'lon' => $this->faker->longitude,
            'point_count' => $pointCount * 1000,
            'point_count_abbreviated' => "{$pointCount}k",
            'geohash' => 'gcpvn219nm0ughyj8uemwkpb',
            'zoom' => $this->faker->randomElement(range(6, 18))
        ];
    }
}

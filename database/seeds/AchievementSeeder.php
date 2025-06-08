<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;

class AchievementsSeeder extends Seeder
{
    private array $milestones;

    public function run(): void
    {
        $this->milestones = Config::get('achievements.milestones');

        // Seed dimension-wide achievements
        $this->seedDimensionWide();

        // Seed per-tag achievements
        $this->seedPerTag();

        // Only output if running from command
        if ($this->command) {
            $this->command->info('Achievements seeded successfully!');
        }
    }

    /**
     * Seed dimension-wide achievements (uploads, total objects, etc.)
     */
    private function seedDimensionWide(): void
    {
        $types = [
            'uploads',
            'objects',
            'categories',
            'materials',
            'brands',
            'streak',
        ];

        foreach ($types as $type) {
            foreach ($this->milestones as $milestone) {
                DB::table('achievements')->updateOrInsert(
                    [
                        'type' => $type,
                        'tag_id' => null,
                        'threshold' => $milestone,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    /**
     * Seed per-tag achievements
     */
    private function seedPerTag(): void
    {
        $tagTypes = [
            'object' => [
                'model' => LitterObject::class,
                'name_field' => 'key',
            ],
            'category' => [
                'model' => Category::class,
                'name_field' => 'key',
            ],
            'material' => [
                'model' => Materials::class,
                'name_field' => 'key',
            ],
            'brand' => [
                'model' => BrandList::class,
                'name_field' => 'key',
            ],
            'customTag' => [
                'model' => CustomTagNew::class,
                'name_field' => 'key',
            ],
        ];

        foreach ($tagTypes as $type => $config) {
            $tags = $config['model']::all();

            foreach ($tags as $tag) {
                foreach ($this->milestones as $milestone) {
                    DB::table('achievements')->updateOrInsert(
                        [
                            'type' => $type,
                            'tag_id' => $tag->id,
                            'threshold' => $milestone,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }
    }
}

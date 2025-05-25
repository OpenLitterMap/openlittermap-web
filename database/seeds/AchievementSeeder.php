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
    private const DEFAULT_MILESTONES = [1, 10, 42, 69, 420, 1337, 42069, 69420];

    private array $milestones;
    private array $xpScale;

    public function run(): void
    {
        $this->milestones = Config::get('achievements.milestones', self::DEFAULT_MILESTONES);
        $this->xpScale = Config::get('achievements.xp_scale', $this->getDefaultXpScale());

        // Seed dimension-wide achievements
        $this->seedDimensionWide();

        // Seed per-tag achievements
        $this->seedPerTag();
    }

    /**
     * Seed dimension-wide achievements (uploads, total objects, etc.)
     */
    private function seedDimensionWide(): void
    {
        $types = [
            'uploads' => ['name' => 'Total Uploads'],
            'objects' => ['name' => 'Total Objects Tagged'],
            'categories' => ['name' => 'Unique Categories Used'],
            'materials' => ['name' => 'Total Materials Tagged'],
            'brands' => ['name' => 'Total Brands Tagged'],
        ];

        foreach ($types as $type => $meta) {
            foreach ($this->milestones as $milestone) {
                DB::table('achievements')->updateOrInsert(
                    [
                        'type' => $type,
                        'tag_id' => null,
                        'threshold' => $milestone,
                    ],
                    [
                        'xp' => $this->getXpForMilestone($milestone),
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
                    $tagName = $tag->{$config['name_field']} ?? 'Unknown';

                    DB::table('achievements')->updateOrInsert(
                        [
                            'type' => $type,
                            'tag_id' => $tag->id,
                            'threshold' => $milestone,
                        ],
                        [
                            'xp' => $this->getXpForMilestone($milestone),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }

        $this->command->info('Achievements seeded successfully!');
    }

    /**
     * Get XP value for a milestone
     */
    private function getXpForMilestone(int $milestone): int
    {
        foreach ($this->xpScale as $threshold => $xp) {
            if ($milestone <= $threshold) {
                return $xp;
            }
        }

        // Default for very high milestones
        return 200;
    }

    /**
     * Get default XP scale
     */
    private function getDefaultXpScale(): array
    {
        return [
            1 => 5,
            10 => 10,
            42 => 20,
            69 => 30,
            420 => 50,
            1337 => 100,
            42069 => 150,
            69420 => 200,
        ];
    }
}

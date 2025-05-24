<?php

namespace Database\Seeders\Achievements;

use App\Models\Achievements\Achievement;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class GenerateAchievementsSeeder extends Seeder
{
    private array $milestones = [];

    public function run(): void
    {
        DB::disableQueryLog();

        echo "Generating achievements...\n";

        $this->milestones = config('achievements.milestones', []);

        DB::transaction(function () {
            $this->generateMilestoneAchievements();
            // $this->generateSpecialAchievements(); we will do this later.
        });
    }

    private function generateMilestoneAchievements(): void
    {
        $this->seedUploads();
        $this->seedDimension('object'   , LitterObject::query());
        $this->seedDimension('category' , Category    ::query());
        $this->seedDimension('material' , Materials   ::query());
        $this->seedDimension('brand'    , BrandList   ::query());
    }

    private function seedUploads(): self
    {
        $rows = collect($this->milestones)->map(fn ($m) => [
            'slug'      => "uploads-{$m}",
            'dimension' => 'uploads',
            'threshold' => $m,
            'xp'        => $this->xp($m, 'uploads'),
            'created_at'=> now(),
            'updated_at'=> now(),
        ])->all();

        Achievement::upsert($rows, ['slug']);
        $this->command->info(sprintf('→ uploads:   %d', count($rows)));

        return $this;
    }

    /**
     * @param LazyCollection|Builder $source
     */
    private function seedDimension(string $dim, $source): self
    {
        $source->orderBy('id')->chunk(500, function ($models) use ($dim) {
            $rows = [];
            foreach ($models as $model) {
                foreach ($this->milestones as $m) {
                    $rows[] = [
                        'slug'       => "{$dim}-{$model->id}-{$m}",
                        'dimension'  => $dim,
                        'tag_id'     => $model->id,
                        'threshold'  => $m,
                        'xp'         => $this->xp($m, $dim),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // split into smaller chunks to avoid too many placeholders
            foreach (array_chunk($rows, 500) as $chunk) {
                Achievement::upsert($chunk, ['slug']);
            }

            $this->command->info(sprintf('→ %-9s %6d', $dim, count($rows)));
        });

        return $this;
    }

    private function xp(int $threshold, string $dim): int
    {
        // You can tune these in one spot instead of magic numbers
        $base = [
            'uploads'  => 12,
            'object'   => 4,
            'category' => 6,
            'material' => 5,
            'brand'    => 7,
        ][$dim] ?? 5;

        $mult = match (true) {
            $threshold >= 10000 => 20,
            $threshold >=  5000 => 15,
            $threshold >=  1000 =>  8,
            $threshold >=   500 =>  5,
            $threshold >=   100 =>  3,
            $threshold >=    50 =>  2,
            default            =>  1,
        };

        return $base * $mult;
    }
}

<?php

namespace App\Console\Commands\Tags;

use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Console\Command;

class SeedTagsCommand extends Command
{
    protected $signature = 'seed:tags';

    protected $description = 'Call the GenerateTagsSeeder command to seed the database with tags';

    public function handle()
    {
        (new GenerateTagsSeeder)->run();
        (new GenerateBrandsSeeder)->run();

        $this->info('Tags seeded successfully!');
    }
}

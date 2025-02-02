<?php

namespace Database\Seeders\Tests;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LoadTagsSeeder extends Seeder
{
    /**
     * Load the pre-compiled tags into the database during tests
     *
     * Before running this seeder, make sure to run the following command:
     * 1. LitterModelSeeder will populate your database with the data.
     *    However, this command is too memory-intensive to re-run during tests.
     *    Therefore, we post-compile the data into json and load them on demand in this seeder.
     *    To generate the json:
     * 2. Run CastLitterModelsToJson.php
     * 3. Now you can import them on demand with this post-compiled seeder.
     */
    public function run(): void
    {
        $tables = [
            'categories' => 'seeders/categories.json',
            'materials' => 'seeders/materials.json',
            'litter_objects' => 'seeders/litter_objects.json',
            'tag_types' => 'seeders/tag_types.json',
            'litter_models' => 'seeders/litter_models.json',
            'litter_model_materials' => 'seeders/litter_model_materials.json',
            'materialables' => 'seeders/materialables.json',
        ];

        foreach ($tables as $table => $path)
        {
            if (Storage::disk('local')->exists($path))
            {
                $data = json_decode(Storage::disk('local')->get($path), true);

                if (!empty($data)) {
                    DB::table($table)->insert($data);
                }
            }
        }
    }
}

<?php

namespace App\Console\Commands\Tags;

use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterModel;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\TagType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CastLitterModelsToJson extends Command
{
    protected $signature = 'olm:seeder:generate-json';

    protected $description = 'After seeding LitterModelSeeder, cast the results to json before testing';

    public function handle (): void
    {
        // Save all model files as json to make testing easier
        $tables = [
            'categories' => Category::select('id', 'key')->get()->toArray(),
            'materials' => Materials::select('id', 'key')->get()->toArray(),
            'litter_objects' => LitterObject::select('id', 'key')->get()->toArray(),
            'tag_types' => TagType::select('id', 'key')->get()->toArray(),
            'litter_models' => LitterModel::select(
                'id',
                'category_id',
                'litter_object_id',
                'tag_type_id'
            )->get()->toArray(),
            'litter_model_materials' => DB::table('litter_model_materials')
                ->select('id', 'litter_model_id', 'material_id')
                ->get()
                ->toArray(),
            'materialables' => DB::table('materialables')
                ->select('id', 'materials_id', 'materialable_id', 'materialable_type')
                ->get()
                ->toArray(),
        ];

        foreach ($tables as $name => $records) {
            $filename = "seeders/{$name}.json";

            if (Storage::disk('local')->exists($filename)) {
                Storage::disk('local')->delete($filename);
            }

            Storage::disk('local')->put($filename, json_encode($records, JSON_PRETTY_PRINT));

            $this->info("Exported {$name} (" . count($records) . " records) to {$filename}");
        }
    }
}

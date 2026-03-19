<?php

namespace Tests\Feature\Tags;

use App\Models\Litter\Tags\LitterObjectType;
use App\Models\Litter\Tags\Materials;
use App\Tags\TagsConfig;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Tests\TestCase;

class TagsConfigValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GenerateTagsSeeder::class);
    }

    /** @test */
    public function every_material_in_config_exists_in_database(): void
    {
        $config = TagsConfig::get();
        $missing = [];

        foreach ($config as $categoryKey => $objects) {
            foreach ($objects as $objectKey => $attributes) {
                foreach ($attributes['materials'] ?? [] as $materialKey) {
                    if (!Materials::where('key', $materialKey)->exists()) {
                        $missing[] = "{$categoryKey}/{$objectKey}: {$materialKey}";
                    }
                }
            }
        }

        $this->assertEmpty($missing, 'Materials missing from DB: ' . implode(', ', $missing));
    }

    /** @test */
    public function every_type_in_config_exists_in_database(): void
    {
        $config = TagsConfig::get();
        $missing = [];

        foreach ($config as $categoryKey => $objects) {
            foreach ($objects as $objectKey => $attributes) {
                foreach ($attributes['types'] ?? [] as $typeKey) {
                    if (!LitterObjectType::where('key', $typeKey)->exists()) {
                        $missing[] = "{$categoryKey}/{$objectKey}: {$typeKey}";
                    }
                }
            }
        }

        $this->assertEmpty($missing, 'Types missing from DB: ' . implode(', ', $missing));
    }

    /** @test */
    public function no_non_material_substances_in_config(): void
    {
        $nonMaterials = ['biodegradable', 'adhesive', 'oil', 'chemical', 'rope'];
        $config = TagsConfig::get();
        $violations = [];

        foreach ($config as $categoryKey => $objects) {
            foreach ($objects as $objectKey => $attributes) {
                foreach ($attributes['materials'] ?? [] as $materialKey) {
                    if (in_array($materialKey, $nonMaterials)) {
                        $violations[] = "{$categoryKey}/{$objectKey}: {$materialKey}";
                    }
                }
            }
        }

        $this->assertEmpty($violations, 'Non-material substances found: ' . implode(', ', $violations));
    }
}

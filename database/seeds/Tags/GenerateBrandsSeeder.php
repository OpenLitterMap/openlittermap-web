<?php

namespace Database\Seeders\Tags;

use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Tags\BrandList;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GenerateBrandsSeeder extends Seeder
{
    public function run (): void
    {
        // Insert previously hard-coded
        $brands = Brand::types();

        foreach ($brands as $brand) {
            BrandList::firstOrCreate([
                'key' => $brand,
                'is_custom' => false
            ]);
        }

        // Insert community created
        $filePath = storage_path('app/seeders/brands.txt');

        if (file_exists($filePath))
        {
            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            $fileBrands = [];
            foreach ($lines as $line) {
                $line = trim($line);
                // Use a regex to match any of the expected prefixes (e.g. "brand;", "Brand:", "brande:" or "Brands:")
                // followed by any whitespace and then the brand name.
                if (preg_match('/^(?:brand(?:s|e)?)[;:]\s*(.+)$/i', $line, $matches)) {
                    $brandName = trim($matches[1]);
                    if (!empty($brandName)) {
                        // Adjust the key 'name' if your table uses a different column name.
                        $fileBrands[] = ['key' => $brandName];
                    }
                }
            }

            // Insert the brands in one batch insert if any valid brands were found.
            if (!empty($fileBrands)) {
                DB::table('brandslist')->insertOrIgnore($fileBrands);

                $this->command->info("Successfully imported " . count($fileBrands) . " brands.");
            } else {
                $this->command->info("No valid brands found in the file.");
            }
        } else {
            $this->command->warn("File not found: {$filePath}");
        }
    }
}

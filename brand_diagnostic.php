// Paste this into: php artisan tinker

// Get sample photo IDs from your current view
$photoIds = DB::table('photos')
    ->where('verified', '>=', 2)
    ->whereBetween('lat', [51.887239, 51.889643])
    ->whereBetween('lon', [-8.494631, -8.478409])
    ->limit(100)
    ->pluck('id');

echo "Found {$photoIds->count()} photos\n\n";

// Check 1: New system - photo_tag_extra_tags with brand type
$newBrands = DB::table('photo_tags')
    ->join('photo_tag_extra_tags', 'photo_tags.id', '=', 'photo_tag_extra_tags.photo_tag_id')
    ->whereIn('photo_tags.photo_id', $photoIds)
    ->where('photo_tag_extra_tags.tag_type', 'brand')
    ->count();

echo "1. NEW SYSTEM - photo_tag_extra_tags with tag_type='brand': {$newBrands}\n";

// Check 2: Does photo_tag_extra_tags have ANY data for these photos?
$anyExtraTags = DB::table('photo_tags')
    ->join('photo_tag_extra_tags', 'photo_tags.id', '=', 'photo_tag_extra_tags.photo_tag_id')
    ->whereIn('photo_tags.photo_id', $photoIds)
    ->count();

echo "2. Total photo_tag_extra_tags entries: {$anyExtraTags}\n";

// Check 3: What tag_types exist?
if ($anyExtraTags > 0) {
    $tagTypes = DB::table('photo_tags')
        ->join('photo_tag_extra_tags', 'photo_tags.id', '=', 'photo_tag_extra_tags.photo_tag_id')
        ->whereIn('photo_tags.photo_id', $photoIds)
        ->distinct()
        ->pluck('tag_type');
    echo "3. Tag types found: " . $tagTypes->implode(', ') . "\n";
}

echo "\n";

// Check 4: OLD SYSTEM - Does 'brands' table exist?
try {
    $oldBrandsTable = DB::table('brands')->whereIn('photo_id', $photoIds)->count();
    echo "4. OLD SYSTEM - 'brands' table exists with {$oldBrandsTable} entries\n";
    
    if ($oldBrandsTable > 0) {
        // Show sample columns
        $sample = DB::table('brands')->whereIn('photo_id', $photoIds)->first();
        if ($sample) {
            $columns = array_keys((array)$sample);
            echo "   Columns: " . implode(', ', $columns) . "\n";
        }
    }
} catch (\Exception $e) {
    echo "4. OLD SYSTEM - 'brands' table does not exist\n";
}

echo "\n";

// Check 5: Is brandslist table populated?
try {
    $brandslistCount = DB::table('brandslist')->count();
    echo "5. brandslist table has {$brandslistCount} total brands\n";
    
    if ($brandslistCount > 0) {
        $sampleBrands = DB::table('brandslist')->limit(5)->pluck('key');
        echo "   Sample brands: " . $sampleBrands->implode(', ') . "\n";
    }
} catch (\Exception $e) {
    echo "5. brandslist table does not exist\n";
}

echo "\n=== DIAGNOSIS ===\n";
if ($newBrands > 0) {
    echo "✅ You have brands in the NEW system (photo_tag_extra_tags)\n";
} elseif ($oldBrandsTable > 0) {
    echo "⚠️  You have brands in the OLD system (brands table)\n";
    echo "    → Need to update BrandAggregator to read from old 'brands' table\n";
} else {
    echo "❌ No brand data found in either system\n";
    echo "    → Either brands weren't tagged, or stored in a different location\n";
}

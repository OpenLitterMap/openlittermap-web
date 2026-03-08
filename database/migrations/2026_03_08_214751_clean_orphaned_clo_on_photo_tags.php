<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix photo_tags with category_litter_object_id = 112 (unclassified/other).
     *
     * Two populations:
     * 1) 18k rows: CLO 112 + null FKs → extra-tag-only containers. Null out CLO.
     * 2) 131k rows: CLO 112 + real FKs (e.g. softdrinks/energy_can) → v4 migration
     *    set wrong CLO. Fix CLO to match actual category_id + litter_object_id.
     */
    public function up(): void
    {
        // 1) Null out CLO on rows with no real object (extra-tag-only containers)
        DB::table('photo_tags')
            ->where('category_litter_object_id', 112)
            ->whereNull('category_id')
            ->whereNull('litter_object_id')
            ->update(['category_litter_object_id' => null]);

        // 2) Fix CLO on rows where real FKs exist but CLO was wrongly set to 112
        DB::statement('
            UPDATE photo_tags pt
            JOIN category_litter_object clo
                ON clo.category_id = pt.category_id
                AND clo.litter_object_id = pt.litter_object_id
            SET pt.category_litter_object_id = clo.id
            WHERE pt.category_litter_object_id = 112
                AND pt.category_id IS NOT NULL
                AND pt.litter_object_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        // Not reversible — the original CLO 112 assignments were incorrect
    }
};

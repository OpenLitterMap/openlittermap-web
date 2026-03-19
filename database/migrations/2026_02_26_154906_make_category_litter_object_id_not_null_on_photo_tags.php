<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfill NULL category_litter_object_id values, then make column NOT NULL.
     *
     * Three cases:
     * 1. category_id + litter_object_id both set → lookup CLO from pivot
     * 2. category_id set, litter_object_id NULL → map to CLO(category, other)
     * 3. Both NULL (brand-only/custom-only shell tags) → map to CLO(unclassified, other)
     */
    public function up(): void
    {
        // Case 1: Both category_id and litter_object_id are set — join to find CLO id
        DB::statement("
            UPDATE photo_tags pt
            JOIN category_litter_object clo
                ON clo.category_id = pt.category_id
                AND clo.litter_object_id = pt.litter_object_id
            SET pt.category_litter_object_id = clo.id
            WHERE pt.category_litter_object_id IS NULL
                AND pt.category_id IS NOT NULL
                AND pt.litter_object_id IS NOT NULL
        ");

        // Case 2: category_id set but litter_object_id NULL → CLO(category, other)
        $otherObjectId = DB::table('litter_objects')->where('key', 'other')->value('id');

        if ($otherObjectId) {
            DB::statement("
                UPDATE photo_tags pt
                JOIN category_litter_object clo
                    ON clo.category_id = pt.category_id
                    AND clo.litter_object_id = ?
                SET pt.category_litter_object_id = clo.id,
                    pt.litter_object_id = ?
                WHERE pt.category_litter_object_id IS NULL
                    AND pt.category_id IS NOT NULL
                    AND pt.litter_object_id IS NULL
            ", [$otherObjectId, $otherObjectId]);
        }

        // Case 3: Both NULL → CLO(unclassified, other)
        $unclassifiedOtherCloId = DB::table('category_litter_object as clo')
            ->join('categories as c', 'c.id', '=', 'clo.category_id')
            ->join('litter_objects as lo', 'lo.id', '=', 'clo.litter_object_id')
            ->where('c.key', 'unclassified')
            ->where('lo.key', 'other')
            ->value('clo.id');

        if ($unclassifiedOtherCloId) {
            $clo = DB::table('category_litter_object')->find($unclassifiedOtherCloId);

            DB::table('photo_tags')
                ->whereNull('category_litter_object_id')
                ->whereNull('category_id')
                ->whereNull('litter_object_id')
                ->update([
                    'category_litter_object_id' => $unclassifiedOtherCloId,
                    'category_id' => $clo->category_id,
                    'litter_object_id' => $clo->litter_object_id,
                ]);
        }

        // Log any remaining NULLs (shouldn't exist but safety check)
        $remaining = DB::table('photo_tags')->whereNull('category_litter_object_id')->count();
        if ($remaining > 0) {
            throw new \RuntimeException(
                "Migration cannot proceed: {$remaining} photo_tags rows still have NULL category_litter_object_id. "
                . "These need manual resolution before making the column NOT NULL."
            );
        }

        // Make NOT NULL
        Schema::table('photo_tags', function (Blueprint $table) {
            $table->unsignedBigInteger('category_litter_object_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photo_tags', function (Blueprint $table) {
            $table->unsignedBigInteger('category_litter_object_id')->nullable()->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop Phase A deprecated columns and redundant indexes from the photos table.
 *
 * 9 columns with zero active code references.
 * 5 redundant/unused indexes.
 * 1 FK constraint (political_id → politicals).
 *
 * See readme/audit/PhotosTableAudit.md for full grep evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            // Drop FK before dropping political_id column
            $table->dropForeign('photos_political_id_foreign');

            // Drop redundant/unused indexes
            $table->dropIndex('total_tags_idx');
            $table->dropIndex('photos_geohash_index');
            $table->dropIndex('idx_photos_verified_lat_lon');
            $table->dropIndex('idx_photos_verified_tile');
            $table->dropIndex('idx_photos_tile_key');

            // Drop columns
            $table->dropColumn([
                'incorrect_verification',   // 0 matches in app/
                'generated',                // 0 matches in app/
                'suburb',                   // Only Suburb.php model $fillable
                'state_district',           // 0 matches in app/
                'wrong_tags',               // 0 matches in app/
                'wrong_tags_by',            // 0 matches in app/
                'geohash',                  // Replaced by geom spatial column + idx_photos_fast_cluster
                'total_litter',             // Code refs removed 2026-03-14, all endpoints use total_tags
                'political_id',             // 0 matches in app/, FK dropped above
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            // Restore columns
            $table->unsignedInteger('incorrect_verification')->default(0)->after('city_id');
            $table->boolean('generated')->default(false)->after('pathways_id');
            $table->string('suburb')->nullable()->after('result_string');
            $table->string('state_district')->nullable()->after('suburb');
            $table->boolean('wrong_tags')->default(false)->after('bbox_assigned_to');
            $table->unsignedInteger('wrong_tags_by')->nullable()->after('wrong_tags');
            $table->string('geohash')->nullable()->after('bounding_box');
            $table->unsignedInteger('total_litter')->default(0)->after('tile_key');
            $table->unsignedInteger('political_id')->nullable()->after('trashdog_id');

            // Restore FK
            $table->foreign('political_id')
                ->references('id')->on('politicals')
                ->onDelete('set null');

            // Restore indexes
            $table->index(['created_at', 'country_id', 'state_id', 'city_id', 'total_tags'], 'total_tags_idx');
            $table->index('geohash', 'photos_geohash_index');
            $table->index(['verified', 'lat', 'lon'], 'idx_photos_verified_lat_lon');
            $table->index(['verified', 'tile_key'], 'idx_photos_verified_tile');
            $table->index('tile_key', 'idx_photos_tile_key');
        });
    }
};

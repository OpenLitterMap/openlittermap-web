<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Covering index for profile location counts query:
     * SELECT COUNT(DISTINCT country_id), COUNT(DISTINCT state_id), COUNT(DISTINCT city_id)
     * FROM photos WHERE user_id = ? AND country_id IS NOT NULL AND deleted_at IS NULL
     */
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->index(
                ['user_id', 'deleted_at', 'country_id', 'state_id', 'city_id'],
                'photos_user_location_counts_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex('photos_user_location_counts_idx');
        });
    }
};

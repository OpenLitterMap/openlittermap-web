<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop stale counters from teams table
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['total_images', 'total_litter', 'images_remaining']);
        });

        // Drop stale counters from team_user pivot
        Schema::table('team_user', function (Blueprint $table) {
            $table->dropColumn(['total_photos', 'total_litter']);
        });

        // Drop dirty_teams table (replaced by on-demand reclustering)
        Schema::dropIfExists('dirty_teams');
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->integer('total_images')->default(0)->after('members');
            $table->integer('total_litter')->default(0)->after('total_images');
            $table->integer('images_remaining')->default(0)->after('members');
        });

        Schema::table('team_user', function (Blueprint $table) {
            $table->integer('total_photos')->default(0);
            $table->integer('total_litter')->default(0);
        });

        Schema::create('dirty_teams', function (Blueprint $table) {
            $table->unsignedInteger('team_id')->primary();
            $table->timestamp('changed_at')->useCurrent();
            $table->unsignedTinyInteger('attempts')->default(0);
        });
    }
};

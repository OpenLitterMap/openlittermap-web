<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();          // e.g. "first-upload"
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('xp')->default(0); // XP granted when unlocked
            $table->unsignedTinyInteger('tier')->default(1); // bronze/silver/gold etc.
            $table->string('icon')->nullable();        // local path or S3 key
            $table->string('reward_type')->nullable(); // "badge", "geonft", "ai_banner", …
            $table->json('meta')->nullable();          // anything else
            $table->timestamps();
        });

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();

            $table->timestamp('unlocked_at');
            $table->unsignedInteger('progress')->default(0);
            $table->unsignedInteger('target')->default(0);
            $table->json('snapshot')->nullable(); // state taken at unlock time
            $table->unique(['user_id', 'achievement_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('xp_new')->default(0);
            $table->unsignedInteger('level_new')->default(0);
            $table->timestamp('leveled_up_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['xp_new', 'level_new', 'leveled_up_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    ┌ achievements ───────────────────────────────────────────────┐
    │ slug        uploads-42 / object-17-100 / streak-7           │
    │ dimension   uploads | object | category | material | brand  │
    │ tag_id      nullable (e.g. object_id)                       │
    │ threshold   INT                                            │
    │ xp          INT                                            │
    └─────────────────────────────────────────────────────────────┘
    */
    public function up(): void
    {
        // definition of each achievement. Pre-populated with all existing tags.
        Schema::create('achievements', function (Blueprint $t) {
            $t->string('slug', 191)->primary();
            $t->string('dimension', 32)->nullable();
            $t->unsignedBigInteger('tag_id')->nullable();
            $t->unsignedInteger('threshold');
            $t->unsignedInteger('xp')->default(0);
            $t->json('meta')->nullable();          // icon / text / i18n
            $t->timestamps();

            $t->unique(['dimension', 'tag_id', 'threshold']);
            $t->index(['dimension', 'threshold']);
        });

        /*
          user_achievements is purely a pivot – no progress columns
          (progress lives in Redis and is re-computed in 2 ms).
        */
        Schema::create('user_achievements', function (Blueprint $t) {
            $t->unsignedInteger('user_id');
            $t->foreign('user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();
            $t->string('achievement_slug', 191);
            $t->timestamp('unlocked_at')->useCurrent();

            $t->primary(['user_id', 'achievement_slug']);
            $t->foreign('achievement_slug')
                ->references('slug')->on('achievements')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
    }
};

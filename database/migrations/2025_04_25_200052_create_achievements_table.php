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
            $t->id();                                       // bigint PK, auto-increment
            $t->string('type', 50);                         // 'uploads', 'object', 'category', etc.
            $t->unsignedBigInteger('tag_id')->nullable();   // null for dimension-wide achievements
            $t->unsignedInteger('threshold');               // Required count to unlock
            $t->unsignedInteger('xp');                      // XP awarded
            $t->json('metadata')->nullable();               // For i18n, icons, descriptions
            $t->timestamps();

            // Composite unique constraint
            $t->unique(['type', 'tag_id', 'threshold']);

            // Indexes for efficient queries
            $t->index(['type', 'threshold']);
            $t->index('type');
        });

        Schema::create('user_achievements', function (Blueprint $t) {
            $t->unsignedInteger('user_id');
            $t->unsignedBigInteger('achievement_id');
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->nullable();

            $t->primary(['user_id', 'achievement_id']);
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $t->foreign('achievement_id')->references('id')->on('achievements')->cascadeOnDelete();

            $t->index('user_id');
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

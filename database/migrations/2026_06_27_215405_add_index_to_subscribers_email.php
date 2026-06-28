<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index subscribers.email — the send command anti-joins on it
 * (whereNotIn user emails) and the subscribe endpoint checks uniqueness on it
 * (unique:subscribers) on every signup. Plain index, not unique: legacy data
 * may contain duplicate addresses (uniqueness was only ever validated, never
 * DB-enforced), which would fail a unique index — dedup is a separate cleanup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->index('email', 'idx_subscribers_email');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropIndex('idx_subscribers_email');
        });
    }
};

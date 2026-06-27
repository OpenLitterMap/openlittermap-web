<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forward-compat Laravel-standard email-verification timestamp. From this
 * release User::confirmEmail() dual-writes it alongside the legacy
 * `users.verified` boolean; `email:backfill-verified-at` backfills existing
 * verified users. `verified` is kept (deprecated) for not-yet-migrated readers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('verified');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};

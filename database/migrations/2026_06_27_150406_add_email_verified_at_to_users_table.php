<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forward-compat column for email verification. The app's legacy verification
 * state lives in `users.verified` (boolean), set via User::confirmEmail() when
 * the welcome "Verify Your Email" button is pressed. This adds the
 * Laravel-standard `email_verified_at` timestamp; from this release the verify
 * handler dual-writes both. A prod backfill (see readme/sql/) sets
 * email_verified_at = created_at for existing verified users.
 *
 * `verified` is kept (deprecated) for not-yet-migrated readers; a later pass
 * migrates remaining readers and drops it.
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop 27 deprecated columns from the users table.
 *
 * All columns have zero active reads in application code.
 * See readme/audit/UsersTableAudit.md for full grep evidence.
 *
 * The users_role_id_index is dropped automatically with the role_id column.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                // v4 per-category counters (12 columns) — all 0 matches in app/
                'total_smoking',
                'total_food',
                'total_softdrinks',
                'total_alcohol',
                'total_coffee',
                'total_sanitary',
                'total_other',
                'total_dumping',
                'total_industrial',
                'total_coastal',
                'total_art',
                'total_dogshit',

                // Stale aggregate counters (4 columns) — replaced by metrics table
                'total_images',
                'total_litter',
                'total_verified',
                'total_verified_litter',

                // Upload tracking (3 columns) — write-only, never read
                'has_uploaded',
                'has_uploaded_today',
                'has_uploaded_counter',

                // Quota columns (2 columns) — written on registration, never read
                'images_remaining',
                'verify_remaining',

                // Misc dead columns
                'billing_id',           // 0 matches in app/
                'role_id',              // Spatie Permission replaced it; also drops users_role_id_index
                'count_correctly_verified', // $fillable only, write-only
                'enable_admin_tagging',     // $fillable only, write-only
                'link_instagram',           // $fillable only, write-only
                'photos_per_month',         // GenerateTimeSeries writes to locations, not users
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // v4 per-category counters
            $table->unsignedInteger('total_smoking')->nullable()->after('xp');
            $table->unsignedInteger('total_food')->nullable()->after('total_smoking');
            $table->unsignedInteger('total_softdrinks')->nullable()->after('total_food');
            $table->unsignedInteger('total_alcohol')->nullable()->after('total_softdrinks');
            $table->unsignedInteger('total_coffee')->nullable()->after('total_alcohol');
            $table->unsignedInteger('total_sanitary')->nullable()->after('total_coffee');
            $table->unsignedInteger('total_other')->nullable()->after('total_sanitary');
            $table->unsignedBigInteger('total_dumping')->nullable()->after('total_other');
            $table->unsignedBigInteger('total_industrial')->nullable()->after('total_dumping');
            $table->unsignedBigInteger('total_coastal')->nullable()->after('total_industrial');
            $table->unsignedInteger('total_art')->nullable()->after('total_coastal');
            $table->unsignedBigInteger('total_dogshit')->nullable()->after('total_art');

            // Stale aggregate counters
            $table->unsignedInteger('total_images')->nullable()->after('level');
            $table->integer('total_litter')->default(0)->after('total_images');
            $table->integer('total_verified')->default(0)->after('total_litter');
            $table->unsignedInteger('total_verified_litter')->default(0)->after('total_verified');

            // Upload tracking
            $table->unsignedInteger('has_uploaded')->default(0)->after('verify_remaining');
            $table->unsignedInteger('has_uploaded_today')->default(0)->after('has_uploaded');
            $table->unsignedInteger('has_uploaded_counter')->default(0)->after('has_uploaded_today');

            // Quota columns
            $table->unsignedInteger('images_remaining')->default(0)->after('stripe_id');
            $table->unsignedInteger('verify_remaining')->default(0)->after('images_remaining');

            // Misc
            $table->string('billing_id')->nullable()->after('role_id');
            $table->unsignedInteger('role_id')->nullable()->after('picked_up');
            $table->index('role_id', 'users_role_id_index');
            $table->unsignedInteger('count_correctly_verified')->default(0)->after('littercoin_paid');
            $table->boolean('enable_admin_tagging')->default(false)->after('count_correctly_verified');
            $table->unsignedInteger('link_instagram')->default(0)->after('phone');
            $table->text('photos_per_month')->nullable()->after('remaining_teams');
        });
    }
};

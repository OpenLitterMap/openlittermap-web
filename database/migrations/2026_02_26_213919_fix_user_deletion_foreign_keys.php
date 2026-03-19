<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make photos.user_id nullable and change FKs to SET NULL on delete.
     *
     * This allows GDPR-compliant account deletion while preserving
     * photos as anonymous public contributions to the map.
     */
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            // Drop existing restrictive FKs
            $table->dropForeign('photos_user_id_foreign');
            $table->dropForeign('photos_verified_by_foreign');
        });

        Schema::table('photos', function (Blueprint $table) {
            // Make user_id nullable (deleted user's photos become anonymous)
            $table->unsignedInteger('user_id')->nullable()->change();

            // Re-add FKs with SET NULL on delete
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('verified_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['verified_by']);
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->nullable(false)->change();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            $table->foreign('verified_by')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });
    }
};

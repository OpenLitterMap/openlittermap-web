<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix FK constraints on tables referencing photos.id so that
 * forceDelete() on a Photo cascades cleanly instead of throwing
 * a constraint violation.
 */
return new class extends Migration
{
    public function up(): void
    {
        // badges: drop old FK (no cascade), re-add with set null
        Schema::table('badges', function (Blueprint $table) {
            $table->dropForeign(['photo_id']);
            $table->foreign('photo_id')->references('id')->on('photos')->onDelete('set null');
        });

        // custom_tags: drop old FK (no cascade), re-add with cascade
        Schema::table('custom_tags', function (Blueprint $table) {
            $table->dropForeign(['photo_id']);
            $table->foreign('photo_id')->references('id')->on('photos')->onDelete('cascade');
        });

        // littercoins: drop old FK (no cascade), re-add with set null (column is nullable)
        Schema::table('littercoins', function (Blueprint $table) {
            $table->dropForeign(['photo_id']);
            $table->foreign('photo_id')->references('id')->on('photos')->onDelete('set null');
        });

        // annotations: fix column type (bigint → int unsigned to match photos.id),
        // clean orphaned rows, then add FK
        Schema::table('annotations', function (Blueprint $table) {
            $table->unsignedInteger('photo_id')->change();
        });
        DB::statement('DELETE FROM annotations WHERE photo_id NOT IN (SELECT id FROM photos)');
        Schema::table('annotations', function (Blueprint $table) {
            $table->foreign('photo_id')->references('id')->on('photos')->onDelete('cascade');
        });

        // admin_verification_logs: clean orphaned rows, then add FK
        DB::statement('DELETE FROM admin_verification_logs WHERE photo_id NOT IN (SELECT id FROM photos)');
        Schema::table('admin_verification_logs', function (Blueprint $table) {
            $table->foreign('photo_id')->references('id')->on('photos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // admin_verification_logs: remove FK (didn't exist before)
        Schema::table('admin_verification_logs', function (Blueprint $table) {
            $table->dropForeign(['photo_id']);
        });

        // annotations: remove FK and restore original bigint type
        Schema::table('annotations', function (Blueprint $table) {
            $table->dropForeign(['photo_id']);
        });
        Schema::table('annotations', function (Blueprint $table) {
            $table->unsignedBigInteger('photo_id')->change();
        });

        // badges: restore original FK without cascade
        Schema::table('badges', function (Blueprint $table) {
            $table->dropForeign(['photo_id']);
            $table->foreign('photo_id')->references('id')->on('photos');
        });

        // custom_tags: restore original FK without cascade
        Schema::table('custom_tags', function (Blueprint $table) {
            $table->dropForeign(['photo_id']);
            $table->foreign('photo_id')->references('id')->on('photos');
        });

        // littercoins: restore original FK without cascade
        Schema::table('littercoins', function (Blueprint $table) {
            $table->dropForeign(['photo_id']);
            $table->foreign('photo_id')->references('id')->on('photos');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow PhotoTags without an object (e.g. custom-tag-only, brand-only, material-only).
     */
    public function up(): void
    {
        Schema::table('photo_tags', function (Blueprint $table) {
            $table->unsignedBigInteger('category_litter_object_id')->nullable()->change();
            $table->unsignedBigInteger('category_id')->nullable()->change();
            $table->unsignedBigInteger('litter_object_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('photo_tags', function (Blueprint $table) {
            $table->unsignedBigInteger('category_litter_object_id')->nullable(false)->change();
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
            $table->unsignedBigInteger('litter_object_id')->nullable(false)->change();
        });
    }
};

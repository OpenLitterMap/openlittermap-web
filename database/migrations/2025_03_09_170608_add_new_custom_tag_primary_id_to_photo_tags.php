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
        Schema::table('photo_tags', function (Blueprint $table) {
            $table->unsignedBigInteger('custom_tag_primary_id')->nullable()->after('litter_object_id')->comment('Only use when the custom tag is the primary tag');
            $table->foreign('custom_tag_primary_id')->references('id')->on('custom_tags_new')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photo_tags', function (Blueprint $table) {
            $table->dropForeign(['custom_tag_primary_id']);
            $table->dropColumn('custom_tag_primary_id');
        });
    }
};

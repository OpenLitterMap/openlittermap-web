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
            $table->unsignedBigInteger('category_litter_object_id')->nullable()->after('litter_object_id');
            $table->foreign('category_litter_object_id')->references('id')->on('category_litter_object')->onDelete('cascade');

            $table->foreignId('litter_object_type_id')->nullable()->after('category_litter_object_id')->constrained('litter_object_types')->onDelete('set null');

            $table->index('category_litter_object_id', 'idx_photo_tags_clo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photo_tags', function (Blueprint $table) {
            $table->dropForeign(['litter_object_type_id']);
            $table->dropColumn('litter_object_type_id');
            $table->dropIndex('idx_photo_tags_clo');
            $table->dropForeign(['category_litter_object_id']);
            $table->dropColumn('category_litter_object_id');
        });
    }
};

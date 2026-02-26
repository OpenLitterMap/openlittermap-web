<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_tags', function (Blueprint $table) {
            $table->dropForeign('photo_tags_custom_tag_primary_id_foreign');
            $table->dropColumn('custom_tag_primary_id');
        });

        Schema::table('photo_tag_extra_tags', function (Blueprint $table) {
            $table->dropColumn('index');
        });
    }

    public function down(): void
    {
        Schema::table('photo_tags', function (Blueprint $table) {
            $table->unsignedBigInteger('custom_tag_primary_id')
                ->nullable()
                ->after('litter_object_type_id')
                ->comment('Only use when the custom tag is the primary tag');
            $table->foreign('custom_tag_primary_id')
                ->references('id')
                ->on('custom_tags_new')
                ->nullOnDelete();
        });

        Schema::table('photo_tag_extra_tags', function (Blueprint $table) {
            $table->integer('index')->nullable()->after('quantity');
        });
    }
};

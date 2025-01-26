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
        Schema::create('litter_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('litter_object_id')->constrained('litter_objects');
            $table->foreignId('tag_type_id')->nullable()->constrained('tag_types');

            $table->unique(['category_id', 'litter_object_id', 'tag_type_id']);

            $table->timestamps();
        });

        Schema::table('photo_tags', function (Blueprint $table) {
            $table->foreignId('litter_model_id')
                ->after('photo_id')
                ->nullable()
                ->constrained('litter_models');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photo_tags', function (Blueprint $table) {
            $table->dropForeign(['litter_model_id']);
            $table->dropColumn('litter_model_id');
        });

        Schema::dropIfExists('litter_models');
    }
};

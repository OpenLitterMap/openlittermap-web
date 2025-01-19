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
        Schema::create('category_litter_object_tag_type', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('litter_object_id');
            $table->unsignedBigInteger('tag_type_id');

            $table->primary(['category_id','litter_object_id','tag_type_id']);

            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('litter_object_id')->references('id')->on('litter_objects');
            $table->foreign('tag_type_id')->references('id')->on('tag_types');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_litter_object_tag_type');
    }
};

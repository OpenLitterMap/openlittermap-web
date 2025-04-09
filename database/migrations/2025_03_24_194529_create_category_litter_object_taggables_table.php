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
        Schema::create('taggables', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('category_litter_object_id')->onDelete('cascade');
            $table->foreign('category_litter_object_id')->references('id')->on('category_litter_object')->onDelete('cascade');

            $table->unsignedBigInteger('taggable_id');
            $table->string('taggable_type');
            $table->unsignedInteger('quantity')->default(1);

            $table->unique(['category_litter_object_id', 'taggable_type', 'taggable_id'], 'clo_taggable_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taggables');
    }
};

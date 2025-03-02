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
        Schema::create('photo_tag_extra_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('photo_tag_id');
            $table->foreign('photo_tag_id')->references('id')->on('photo_tags')->onDelete('cascade');

            // Polymorphic relationship
            $table->string('tag_type');
            $table->unsignedBigInteger('tag_type_id');

            $table->integer('quantity')->default(1);
            $table->integer('index')->nullable();
            $table->timestamps();

            $table->index(['tag_type', 'tag_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photo_tag_extras');
    }
};

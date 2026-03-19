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
        Schema::create('photo_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('photo_id');
            $table->foreign('photo_id')->references('id')->on('photos')->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('litter_object_id')->nullable()->constrained()->onDelete('cascade');
            $table->integer('quantity')->nullable();
            $table->boolean('picked_up')->nullable();
            $table->timestamps();

            // Add composite indexes for common queries
            $table->index(['photo_id', 'category_id'], 'idx_photo_category');
            $table->index(['photo_id', 'litter_object_id'], 'idx_photo_object');
            $table->index(['category_id', 'litter_object_id'], 'idx_category_object');

            // Index for aggregation queries
            $table->index(['category_id', 'quantity'], 'idx_category_quantity');
            $table->index(['litter_object_id', 'quantity'], 'idx_object_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photo_tags');
    }
};

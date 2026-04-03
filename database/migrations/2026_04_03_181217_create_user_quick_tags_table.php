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
        Schema::create('user_quick_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('clo_id');
            $table->unsignedInteger('type_id')->nullable();
            $table->unsignedTinyInteger('quantity')->default(1);
            $table->boolean('picked_up')->nullable();
            $table->json('materials');
            $table->json('brands');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('user_id');

            $table->foreign('clo_id')
                ->references('id')
                ->on('category_litter_object')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_quick_tags');
    }
};

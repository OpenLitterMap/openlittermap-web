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
        Schema::create('litter_model_materials', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('litter_model_id');
            $table->unsignedBigInteger('material_id');

            $table->unique(['litter_model_id', 'material_id']);

            $table->foreign('litter_model_id')->references('id')->on('litter_models')->onDelete('cascade');
            $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('litter_model_materials');
    }
};

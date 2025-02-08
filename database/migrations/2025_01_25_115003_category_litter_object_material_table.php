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
        Schema::create('category_litter_object_material', function (Blueprint $table) {
            $table->unsignedBigInteger('category_litter_object_id');
            $table->unsignedBigInteger('material_id');

            // Define a composite primary key.
            $table->primary(['category_litter_object_id', 'material_id']);

            // Add foreign key constraints with custom (shorter) names.
            $table->foreign('category_litter_object_id', 'fk_clom_cloid')
                ->references('id')->on('category_litter_object')
                ->onDelete('cascade');

            $table->foreign('material_id', 'fk_clom_mid')
                ->references('id')->on('materials')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_litter_object_material');
    }
};

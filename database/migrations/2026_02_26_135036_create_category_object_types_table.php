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
        Schema::create('category_object_types', function (Blueprint $table) {
            $table->foreignId('category_litter_object_id')->constrained('category_litter_object')->onDelete('cascade');
            $table->foreignId('litter_object_type_id')->constrained('litter_object_types')->onDelete('cascade');

            $table->unique(['category_litter_object_id', 'litter_object_type_id'], 'cot_clo_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_object_types');
    }
};

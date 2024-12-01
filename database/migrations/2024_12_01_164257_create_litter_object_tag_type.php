<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.O
     */
    public function up(): void
    {
        Schema::create('litter_object_tag_type', function (Blueprint $table) {
            $table->foreignId('litter_object_id')->constrained('litter_objects')->onDelete('cascade');
            $table->foreignId('tag_type_id')->constrained('tag_types')->onDelete('cascade');
            $table->primary(['litter_object_id', 'tag_type_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('litter_object_tag_type');
    }
};

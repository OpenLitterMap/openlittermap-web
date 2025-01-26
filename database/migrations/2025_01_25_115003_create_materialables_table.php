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
        Schema::create('materialables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('materials_id')->constrained('materials')->cascadeOnDelete();
            $table->unsignedBigInteger('materialable_id');
            $table->string('materialable_type');
            $table->timestamps();

            $table->index(['materialable_id', 'materialable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materialables');
    }
};

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
        Schema::create('brandables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brandlist_id')->constrained('brandslist')->cascadeOnDelete();
            $table->unsignedBigInteger('brandable_id');
            $table->string('brandable_type');
            $table->timestamps();

            $table->index(['brandable_id', 'brandable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brandables');
    }
};

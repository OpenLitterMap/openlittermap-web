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
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // photo_upload, category, object, landuse
            $table->string('subtype')->nullable(); // landuse_type
            $table->integer('number')->default(1); // 1st, 2nd, 3rd

            $table->text('filename')->nullable();

            $table->unsignedInteger('photo_id')->nullable();
            $table->foreign('photo_id')->references('id')->on('photos');

            $table->foreignId('photo_tag_id')->nullable()->constrained();
            $table->foreignId('category_id')->nullable()->constrained();
            $table->foreignId('litter_object_id')->nullable()->constrained();

            $table->unsignedInteger('country_id')->nullable();
            $table->unsignedInteger('state_id')->nullable();
            $table->unsignedInteger('city_id')->nullable();
            $table->foreign('country_id')->references('id')->on('countries');
            $table->foreign('state_id')->references('id')->on('states');
            $table->foreign('city_id')->references('id')->on('cities');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};

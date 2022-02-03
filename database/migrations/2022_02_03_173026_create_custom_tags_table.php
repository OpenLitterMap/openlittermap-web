<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomTagsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('custom_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('photo_id');
            $table->string('tag', 100)->index();
            $table->timestamps();

            $table->foreign('photo_id')->references('id')->on('photos');
            $table->unique(['photo_id', 'tag']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('custom_tags');
    }
}

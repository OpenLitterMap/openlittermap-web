<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFoodTableAgain extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Schema::create('food', function (Blueprint $table) {
        //     $table->increments('id');

        //     $table->integer('photo_id')->unsigned();
        //     $table->foreign('photo_id')->references('id')->on('photos')->onDelete('cascade');

        //     $table->integer('sweetWrappers')->unsigned()->nullable();
        //     $table->integer('cardboardFoodPackaging')->unsigned()->nullable();
        //     $table->integer('paperFoodPackaging')->unsigned()->nullable();
        //     $table->integer('plasticFoodPackaging')->unsigned()->nullable();
        //     $table->integer('plasticCutlery')->unsigned()->nullable();
        //     $table->integer('foodOther')->unsigned()->nullable();

        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('food');
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAlcoholTableAgain extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Schema::create('alcohol', function (Blueprint $table) {
        //     $table->increments('id');

        //     $table->integer('photo_id')->unsigned();
        //     $table->foreign('photo_id')->references('id')->on('photos')->onDelete('cascade');

        //     $table->integer('glassAlcoholBottle')->unsigned()->nullable();
        //     $table->integer('glassAlcoholSpirits')->unsigned()->nullable();
        //     $table->integer('alcoholCan')->unsigned()->nullable();
        //     $table->integer('brokenGlass')->unsigned()->nullable();
        //     $table->integer('paperCardAlcoholPackaging')->unsigned()->nullable();
        //     $table->integer('plasticAlcoholPackaging')->unsigned()->nullable();
        //     $table->integer('bottleTops')->unsigned()->nullable();
        //     $table->integer('alcoholOther')->unsigned()->nullable();

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
        Schema::dropIfExists('alcohol');
    }
}

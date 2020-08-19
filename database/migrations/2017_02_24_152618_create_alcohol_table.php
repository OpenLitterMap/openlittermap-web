<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAlcoholTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('alcohol', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('beerBottle')->unsigned()->nullable();
            $table->integer('spiritBottle')->unsigned()->nullable();
            $table->integer('wineBottle')->unsigned()->nullable();
            $table->integer('beerCan')->unsigned()->nullable();
            $table->integer('brokenGlass')->unsigned()->nullable();
            $table->integer('paperCardAlcoholPackaging')->unsigned()->nullable();
            $table->integer('plasticAlcoholPackaging')->unsigned()->nullable();
            $table->integer('bottleTops')->unsigned()->nullable();
            $table->integer('alcoholOther')->unsigned()->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}

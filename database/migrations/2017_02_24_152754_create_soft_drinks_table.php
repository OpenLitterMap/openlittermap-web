<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSoftDrinksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('soft_drinks', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('waterBottle')->unsigned()->nullable();
            $table->integer('fizzyDrinkBottle')->unsigned()->nullable();
            $table->integer('bottleLid')->unsigned()->nullable();
            $table->integer('bottleLabel')->unsigned()->nullable();
            $table->integer('tinCan')->unsigned()->nullable();
            $table->integer('sportsDrink')->unsigned()->nullable();
            $table->integer('softDrinkOther')->unsigned()->nullable();

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
        Schema::dropIfExists('soft_drinks');
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('states', function (Blueprint $table) {
            $table->increments('id');
            $table->string('state');
            $table->integer('country_id')->unsigned();
            $table->foreign('country_id')->references('id')->on('countries');
            $table->integer('total_images')->unsigned()->nullable();
            $table->integer('total_smoking')->unsigned()->nullable();
            $table->integer('total_cigaretteButts')->unsigned()->nullable();
            $table->integer('total_food')->unsigned()->nullable();
            $table->integer('total_softDrinks')->unsigned()->nullable();
            $table->integer('total_plasticBottles')->unsigned()->nullable();
            $table->integer('total_alcohol')->unsigned()->nullable();
            $table->integer('total_coffee')->unsigned()->nullable();
            $table->integer('total_drugs')->unsigned()->nullable();
            $table->integer('total_needles')->unsigned()->nullable();
            $table->integer('total_sanitary')->unsigned()->nullable();
            $table->integer('total_other')->unsigned()->nullable();
            $table->integer('total_coastal')->unsigned()->nullable();
            $table->integer('total_contributors')->unsigned()->nullable();
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
        Schema::dropIfExists('states');
    }
}

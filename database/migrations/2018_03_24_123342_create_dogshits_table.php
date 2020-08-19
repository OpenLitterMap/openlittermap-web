<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDogshitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dogshits', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('warm')->unsigned()->nullable();
            $table->integer('salty')->unsigned()->nullable();
            $table->integer('nutty')->unsigned()->nullable();
            $table->integer('fresh')->unsigned()->nullable();
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
        Schema::dropIfExists('dogshits');
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDonatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('donates', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('one')->unsigned(); // 5 
            $table->integer('two')->unsigned(); // 10
            $table->integer('three')->unsigned(); // 25
            $table->integer('four')->unsigned(); // 50
            $table->integer('five')->unsigned(); // 100
            $table->integer('six')->unsigned(); // 500 
            $table->integer('seven')->unsigned(); // 500
            $table->integer('eight')->unsigned(); // 1000
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
        Schema::dropIfExists('donates');
    }
}

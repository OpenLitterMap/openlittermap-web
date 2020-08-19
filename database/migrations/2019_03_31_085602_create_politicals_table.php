<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePoliticalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('politicals', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('finegael')->unsigned()->nullable();
            $table->integer('finnafail')->unsigned()->nullable();
            $table->integer('greens')->unsigned()->nullable();
            $table->integer('sinnfein')->unsigned()->nullable();
            $table->integer('independent')->unsigned()->nullable();
            $table->integer('labour')->unsigned()->nullable();
            $table->integer('solidarity')->unsigned()->nullable();
            $table->integer('socialdemocrats')->unsigned()->nullable();
            $table->integer('peoplebeforeprofit')->unsigned()->nullable();
            $table->integer('other')->unsigned()->nullable();
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
        Schema::dropIfExists('politicals');
    }
}

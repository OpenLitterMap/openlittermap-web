<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDrugsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('drugs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('needles')->unsigned()->nullable();
            $table->integer('wipes')->unsigned()->nullable();
            $table->integer('tops')->unsigned()->nullable();
            $table->integer('packaging')->unsigned()->nullable();
            $table->integer('waterbottle')->unsigned()->nullable();
            $table->integer('spoons')->unsigned()->nullable();
            $table->integer('needlebin')->unsigned()->nullable();
            $table->integer('usedtinfoil')->unsigned()->nullable();
            $table->integer('barrels')->unsigned()->nullable();
            $table->integer('fullpackage')->unsigned()->nullable();

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
        Schema::dropIfExists('drugs');
    }
}

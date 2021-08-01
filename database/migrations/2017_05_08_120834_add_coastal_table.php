<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCoastalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('coastal', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('microplastics')->unsigned()->nullable();
            $table->integer('mediumplastics')->unsigned()->nullable();
            $table->integer('macroplastics')->unsigned()->nullable();
            $table->integer('rope_small')->unsigned()->nullable();
            $table->integer('rope_medium')->unsigned()->nullable();
            $table->integer('rope_large')->unsigned()->nullable();
            $table->integer('fishing_gear_nets')->unsigned()->nullable();
            $table->integer('buoys')->unsigned()->nullable();
            $table->integer('degraded_plasticbottle')->unsigned()->nullable();
            $table->integer('degraded_plasticbag')->unsigned()->nullable();
            $table->integer('degraded_straws')->unsigned()->nullable();
            $table->integer('degraded_lighters')->unsigned()->nullable();
            $table->integer('balloons')->unsigned()->nullable();
            $table->integer('lego')->unsigned()->nullable();
            $table->integer('shotgun_cartridges')->unsigned()->nullable();
            $table->integer('coastal_other')->unsigned()->nullable();

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
        Schema::dropIfExists('coastal');
    }
}

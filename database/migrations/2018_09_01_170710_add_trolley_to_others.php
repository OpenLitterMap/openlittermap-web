<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTrolleyToOthers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('others', function (Blueprint $table) {
            $table->integer('automobile')->unsigned()->nullable();
            $table->integer('balloons')->unsigned()->nullable();
            $table->integer('clothing')->unsigned()->nullable();
            $table->integer('pooinbag')->unsigned()->nullable();
            $table->integer('traffic_cone')->unsigned()->nullable();
            $table->integer('life_buoy')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('others', function (Blueprint $table) {
            //
        });
    }
}

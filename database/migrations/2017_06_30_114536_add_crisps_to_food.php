<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCrispsToFood extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('food', function (Blueprint $table) {
            $table->integer('crisp_small')->unsigned()->nullable();
            $table->integer('crisp_large')->unsigned()->nullable();
            $table->integer('styrofoam_plate')->unsigned()->nullable();
            $table->integer('napkins')->unsigned()->nullable();
            $table->integer('sauce_packet')->unsigned()->nullable();
            $table->integer('glass_jar')->unsigned()->nullable();
            $table->integer('glass_jar_lid')->unsigned()->nullable(); 
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('food', function (Blueprint $table) {
            //
        });
    }
}

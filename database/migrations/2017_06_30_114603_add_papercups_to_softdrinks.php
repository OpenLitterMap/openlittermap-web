<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPapercupsToSoftdrinks extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('soft_drinks', function (Blueprint $table) {
            $table->integer('paper_cups')->unsigned()->nullable();
            $table->integer('juice_cartons')->unsigned()->nullable();
            $table->integer('juice_bottles')->unsigned()->nullable();
            $table->integer('juice_packet')->unsigned()->nullable();
            $table->integer('ice_tea_bottles')->unsigned()->nullable();
            $table->integer('ice_tea_can')->unsigned()->nullable();
            $table->integer('energy_can')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('soft_drinks', function (Blueprint $table) {
            //
        });
    }
}

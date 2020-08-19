<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNewTotalsToLocations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->integer('total_dumping')->unsigned()->nullable();
            $table->integer('total_industrial')->unsigned()->nullable();
        });

        Schema::table('states', function (Blueprint $table) {
            $table->integer('total_dumping')->unsigned()->nullable();
            $table->integer('total_industrial')->unsigned()->nullable();
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->integer('total_dumping')->unsigned()->nullable();
            $table->integer('total_industrial')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('locations', function (Blueprint $table) {
            //
        });
    }
}

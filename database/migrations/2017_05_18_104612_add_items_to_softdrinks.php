<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddItemsToSoftdrinks extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('soft_drinks', function (Blueprint $table) {
            $table->integer('straws')->unsigned()->nullable();
            $table->integer('plastic_cups')->unsigned()->nullable();
            $table->integer('plastic_cup_tops')->unsigned()->nullable();
            $table->integer('milk_bottle')->unsigned()->nullable();
            $table->integer('milk_carton')->unsigned()->nullable();
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

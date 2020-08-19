<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPizzaBoxesToFood extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('food', function (Blueprint $table) {
            $table->integer('pizza_box')->unsigned()->nullable();
            $table->integer('aluminium_foil')->unsigned()->nullable();
        });

        Schema::table('others', function (Blueprint $table) {
            $table->integer('bags_litter')->unsigned()->nullable();
            $table->integer('cable_tie')->unsigned()->nullable();
            $table->integer('tyre')->unsigned()->nullable();
            $table->integer('overflowing_bins')->unsigned()->nullable();
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

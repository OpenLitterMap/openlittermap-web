<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTotalsToCities extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->integer('total_images')->unsigned()->nullable();
            $table->integer('total_smoking')->unsigned()->nullable();
            $table->integer('total_cigaretteButts')->unsigned()->nullable();
            $table->integer('total_food')->unsigned()->nullable();
            $table->integer('total_softDrinks')->unsigned()->nullable();
            $table->integer('total_plasticBottles')->unsigned()->nullable();
            $table->integer('total_alcohol')->unsigned()->nullable();
            $table->integer('total_coffee')->unsigned()->nullable();
            $table->integer('total_drugs')->unsigned()->nullable();
            $table->integer('total_needles')->unsigned()->nullable();
            $table->integer('total_sanitary')->unsigned()->nullable();
            $table->integer('total_other')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('total_images');
            $table->dropColumn('total_smoking');
            $table->dropColumn('total_cigaretteButts');
            $table->dropColumn('total_food');
            $table->dropColumn('total_softDrinks');
            $table->dropColumn('total_plasticBottles');
            $table->dropColumn('total_alcohol');
            $table->dropColumn('total_coffee');
            $table->dropColumn('total_drugs');
            $table->dropColumn('total_needles');
            $table->dropColumn('total_sanitary');
            $table->dropColumn('total_other');
        });
    }
}

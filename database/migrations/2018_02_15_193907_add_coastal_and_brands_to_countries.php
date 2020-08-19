<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCoastalAndBrandsToCountries extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->integer('total_coastal')->unsigned()->nullable();
            $table->integer('total_adidas')->unsigned()->nullable();
            $table->integer('total_amazon')->unsigned()->nullable();
            $table->integer('total_apple')->unsigned()->nullable();
            $table->integer('total_budweiser')->unsigned()->nullable();
            $table->integer('total_coke')->unsigned()->nullable();
            $table->integer('total_colgate')->unsigned()->nullable();
            $table->integer('total_corona')->unsigned()->nullable();
            $table->integer('total_fritolay')->unsigned()->nullable();
            $table->integer('total_gillette')->unsigned()->nullable();
            $table->integer('total_heineken')->unsigned()->nullable();
            $table->integer('total_kellogs')->unsigned()->nullable();
            $table->integer('total_lego')->unsigned()->nullable();
            $table->integer('total_loreal')->unsigned()->nullable();
            $table->integer('total_nescafe')->unsigned()->nullable();
            $table->integer('total_nestle')->unsigned()->nullable();
            $table->integer('total_marlboro')->unsigned()->nullable();
            $table->integer('total_mcdonalds')->unsigned()->nullable();
            $table->integer('total_nike')->unsigned()->nullable();
            $table->integer('total_pepsi')->unsigned()->nullable();
            $table->integer('total_redbull')->unsigned()->nullable();
            $table->integer('total_samsung')->unsigned()->nullable();
            $table->integer('total_subway')->unsigned()->nullable();
            $table->integer('total_starbucks')->unsigned()->nullable();
            $table->integer('total_tayto')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('countries', function (Blueprint $table) {
            //
        });
    }
}

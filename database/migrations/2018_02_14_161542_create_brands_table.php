<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBrandsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('adidas')->unsigned()->nullable();
            $table->integer('amazon')->unsigned()->nullable();
            $table->integer('apple')->unsigned()->nullable();
            $table->integer('budweiser')->unsigned()->nullable();
            $table->integer('coke')->unsigned()->nullable();
            $table->integer('colgate')->unsigned()->nullable();
            $table->integer('corona')->unsigned()->nullable();
            $table->integer('fritolay')->unsigned()->nullable();
            $table->integer('gillette')->unsigned()->nullable();
            $table->integer('heineken')->unsigned()->nullable();
            $table->integer('kellogs')->unsigned()->nullable();
            $table->integer('lego')->unsigned()->nullable();
            $table->integer('loreal')->unsigned()->nullable();
            $table->integer('nescafe')->unsigned()->nullable();
            $table->integer('nestle')->unsigned()->nullable();
            $table->integer('marlboro')->unsigned()->nullable();
            $table->integer('mcdonalds')->unsigned()->nullable();
            $table->integer('nike')->unsigned()->nullable();
            $table->integer('pepsi')->unsigned()->nullable();
            $table->integer('redbull')->unsigned()->nullable();
            $table->integer('samsung')->unsigned()->nullable();
            $table->integer('subway')->unsigned()->nullable();
            $table->integer('starbucks')->unsigned()->nullable();
            $table->integer('tayto')->unsigned()->nullable();
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
        Schema::dropIfExists('brands');
    }
}

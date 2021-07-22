<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFoodTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('food', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('sweetWrappers')->unsigned()->nullable();
            $table->integer('paperFoodPackaging')->unsigned()->nullable();
            $table->integer('plasticFoodPackaging')->unsigned()->nullable();
            $table->integer('plasticCutlery')->unsigned()->nullable();
            $table->integer('foodOther')->unsigned()->nullable();

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
        //
    }
}

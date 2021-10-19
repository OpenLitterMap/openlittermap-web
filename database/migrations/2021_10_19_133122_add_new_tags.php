<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewTags extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('food', function (Blueprint $table) {
            $table->integer('chewing_gum')->unsigned()->nullable();
        });
        Schema::table('brands', function (Blueprint $table) {
            $table->integer('aadrink')->unsigned()->nullable();
            $table->integer('amstel')->unsigned()->nullable();
            $table->integer('bacardi')->unsigned()->nullable();
            $table->integer('bullit')->unsigned()->nullable();
            $table->integer('caprisun')->unsigned()->nullable();
            $table->integer('fanta')->unsigned()->nullable();
            $table->integer('fernandes')->unsigned()->nullable();
            $table->integer('goldenpower')->unsigned()->nullable();
            $table->integer('hertog_jan')->unsigned()->nullable();
            $table->integer('lavish')->unsigned()->nullable();
            $table->integer('lipton')->unsigned()->nullable();
            $table->integer('monster')->unsigned()->nullable();
            $table->integer('schutters')->unsigned()->nullable();
            $table->integer('slammers')->unsigned()->nullable();
            $table->integer('spa')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}

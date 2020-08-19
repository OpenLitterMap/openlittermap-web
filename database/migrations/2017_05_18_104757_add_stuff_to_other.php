<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStuffToOther extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('others', function (Blueprint $table) {
            $table->integer('plastic_bags')->unsigned()->nullable();
            $table->integer('election_posters')->unsigned()->nullable();
            $table->integer('forsale_posters')->unsigned()->nullable();
            $table->integer('books')->unsigned()->nullable();
            $table->integer('magazine')->unsigned()->nullable();
            $table->integer('paper')->unsigned()->nullable();
            $table->integer('stationary')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('others', function (Blueprint $table) {
            //
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewTagsV2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('alcohol', function (Blueprint $table) {
            $table->integer('cup')->unsigned()->nullable();
            $table->integer('packaging')->unsigned()->nullable();
        });
        Schema::table('coastal', function (Blueprint $table) {
            $table->integer('degraded_bottle')->unsigned()->nullable();
            $table->integer('degraded_bag')->unsigned()->nullable();
        });
        Schema::table('food', function (Blueprint $table) {
            $table->integer('packaging')->unsigned()->nullable();
            $table->integer('cutlery')->unsigned()->nullable();
            $table->integer('jar')->unsigned()->nullable();
            $table->integer('jar_lid')->unsigned()->nullable();
            $table->integer('foil')->unsigned()->nullable();
        });
        Schema::table('softdrinks', function (Blueprint $table) {
            $table->integer('cup')->unsigned()->nullable();
            $table->integer('cup_top')->unsigned()->nullable();
        });
        Schema::table('smoking', function (Blueprint $table) {
            $table->integer('packaging')->unsigned()->nullable();
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

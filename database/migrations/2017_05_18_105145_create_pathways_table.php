<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePathwaysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pathways', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('gutter')->unsigned()->nullable();
            $table->integer('gutter_long')->unsigned()->nullable();
            $table->integer('kerb_hole_small')->unsigned()->nullable();
            $table->integer('kerb_hole_large')->unsigned()->nullable();
            $table->integer('pathwayOther')->unsigned()->nullable();
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
        Schema::dropIfExists('pathways');
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIndustryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('industrial', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('oil')->unsigned()->nullable();
            $table->integer('chemical')->unsigned()->nullable();
            $table->integer('industrial_plastic')->unsigned()->nullable();
            $table->integer('bricks')->unsigned()->nullable();
            $table->integer('tape')->unsigned()->nullable();
            $table->integer('industrial_other')->unsigned()->nullable();
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
        Schema::dropIfExists('industry');
    }
}

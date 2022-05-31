<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMaterialTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('material', function (Blueprint $table) {
            $table->id();
            $table->integer('aluminium')->unsigned()->nullable();
            $table->integer('glass')->unsigned()->nullable();
            $table->integer('metal')->unsigned()->nullable();
            $table->integer('nylon')->unsigned()->nullable();
            $table->integer('paper')->unsigned()->nullable();
            $table->integer('plastic')->unsigned()->nullable();
            $table->integer('polystyrene')->unsigned()->nullable();
            $table->integer('wood')->unsigned()->nullable();
            $table->timestamps();
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->unsignedBigInteger('material_id')->nullable();
            $table->foreign('material_id')->references('id')->on('material');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('material');
    }
}

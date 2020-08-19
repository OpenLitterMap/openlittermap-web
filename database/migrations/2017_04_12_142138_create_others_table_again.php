<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOthersTableAgain extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Schema::create('others', function (Blueprint $table) {
        //     $table->increments('id');

        //     $table->integer('photo_id')->unsigned();
        //     $table->foreign('photo_id')->references('id')->on('photos')->onDelete('cascade');

        //     $table->integer('dogshit')->unsigned()->nullable();
        //     $table->integer('dump')->unsigned()->nullable();
        //     $table->integer('plastic')->unsigned()->nullable();
        //     $table->integer('metal')->unsigned()->nullable();
        //     $table->integer('other')->unsigned()->nullable();

        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('others');
    }
}

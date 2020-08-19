<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSmokingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
       Schema::create('smoking', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('butts')->unsigned()->nullable();
            $table->integer('lighters')->unsigned()->nullable();
            $table->integer('cigaretteBox')->unsigned()->nullable();
            $table->integer('tobaccoPouch')->unsigned()->nullable();
            $table->integer('skins')->unsigned()->nullable();
            $table->integer('smokingOther')->unsigned()->nullable();
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
        Schema::dropIfExists('smoking');
    }
}

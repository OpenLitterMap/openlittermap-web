<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTeamsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->unique();
            $table->integer('members')->unsigned()->default(1);
            $table->integer('images_remaining')->unsigned()->default(0);
            $table->integer('total_images')->unsigned()->default(0);
            $table->integer('total_litter')->unsigned()->default(0);
            $table->integer('leader')->unsigned()->nullable();
            $table->foreign('leader')->references('id')->on('users');
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
        Schema::dropIfExists('teams');
    }
}

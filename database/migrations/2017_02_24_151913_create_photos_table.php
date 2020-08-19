<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePhotosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users');

            $table->string('filename');
            $table->string('model');
            $table->string('datetime');
            $table->boolean('verified')->default(0);
            $table->double('verification')->default(0);
            $table->boolean('remaining')->default(1);

            $table->double('lat')->nullable();
            $table->double('lon')->nullable();

            $table->string('display_name')->nullable(); // full address
            $table->string('location')->nullable();
            $table->string('road')->nullable();
            $table->string('suburb')->nullable();
            $table->string('city')->nullable();
            $table->string('county')->nullable();
            $table->string('state_district')->nullable();
            $table->string('country')->nullable();
            $table->string('country_code')->nullable();
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
        Schema::dropIfExists('photos');
    }
}

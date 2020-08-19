<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNewItemsToAlcohol extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('alcohol', function (Blueprint $table) {
            $table->integer('six_pack_rings')->unsigned()->nullable();
            $table->integer('plastic_cups')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('alcohol', function (Blueprint $table) {
            //
        });
    }
}

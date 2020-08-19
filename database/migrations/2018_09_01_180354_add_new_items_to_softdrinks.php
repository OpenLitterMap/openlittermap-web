<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNewItemsToSoftdrinks extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('soft_drinks', function (Blueprint $table) {
            $table->integer('pullring')->unsigned()->nullable();
            $table->integer('strawpacket')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('soft_drinks', function (Blueprint $table) {
            //
        });
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStyroformToCoastal extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('coastal', function (Blueprint $table) {
            $table->integer('styro_small')->unsigned()->nullable();
            $table->integer('styro_medium')->unsigned()->nullable();
            $table->integer('styro_large')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('coastal', function (Blueprint $table) {
            //
        });
    }
}

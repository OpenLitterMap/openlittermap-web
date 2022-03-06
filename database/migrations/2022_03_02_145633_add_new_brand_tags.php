<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewBrandTags extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->integer('modelo')->unsigned()->nullable();
            $table->integer('anheuser_busch')->unsigned()->nullable();
            $table->integer('molson_coors')->unsigned()->nullable();
            $table->integer('seven_eleven')->unsigned()->nullable();
            $table->integer('acadia')->unsigned()->nullable();
            $table->integer('calanda')->unsigned()->nullable();
            $table->integer('winston')->unsigned()->nullable();
            $table->integer('ok_')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNewBrandsForKeith extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->integer('applegreen')->unsigned()->nullable();
            $table->integer('avoca')->unsigned()->nullable();
            $table->integer('bewleys')->unsigned()->nullable();
            $table->integer('brambles')->unsigned()->nullable();
            $table->integer('butlers')->unsigned()->nullable();
            $table->integer('cafe_nero')->unsigned()->nullable();
            $table->integer('centra')->unsigned()->nullable();
            $table->integer('costa')->unsigned()->nullable();
            $table->integer('esquires')->unsigned()->nullable();
            $table->integer('frank_and_honest')->unsigned()->nullable();
            $table->integer('insomnia')->unsigned()->nullable();
            $table->integer('lolly_and_cookes')->unsigned()->nullable();
            $table->integer('obriens')->unsigned()->nullable();
            $table->integer('supermacs')->unsigned()->nullable();
            $table->integer('wilde_and_greene')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brands', function (Blueprint $table) {
            //
        });
    }
}

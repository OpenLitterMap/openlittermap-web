<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddKeithTotalBrandsToCountries extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->integer('total_applegreen')->unsigned()->nullable();
            $table->integer('total_avoca')->unsigned()->nullable();
            $table->integer('total_bewleys')->unsigned()->nullable();
            $table->integer('total_brambles')->unsigned()->nullable();
            $table->integer('total_butlers')->unsigned()->nullable();
            $table->integer('total_cafe_nero')->unsigned()->nullable();
            $table->integer('total_centra')->unsigned()->nullable();
            $table->integer('total_costa')->unsigned()->nullable();
            $table->integer('total_esquires')->unsigned()->nullable();
            $table->integer('total_frank_and_honest')->unsigned()->nullable();
            $table->integer('total_insomnia')->unsigned()->nullable();
            $table->integer('total_obriens')->unsigned()->nullable();
            $table->integer('total_lolly_and_cookes')->unsigned()->nullable();
            $table->integer('total_supermacs')->unsigned()->nullable();
            $table->integer('total_wilde_and_greene')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('countries', function (Blueprint $table) {
            //
        });
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFiltersToSmoking extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('smoking', function (Blueprint $table) {
            $table->integer('plastic')->unsigned()->nullable();
            $table->integer('filters')->unsigned()->nullable();
            $table->integer('filterbox')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('smoking', function (Blueprint $table) {
            //
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeDefaultTotalLitterOnCities extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->integer('total_litter')->default(1)->change();
        });

        Schema::table('states', function (Blueprint $table) {
            $table->integer('total_litter')->default(1)->change();
            $table->integer('manual_verify')->default(1)->change();
        });
    }
}

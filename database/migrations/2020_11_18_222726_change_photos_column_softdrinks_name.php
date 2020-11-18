<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangePhotosColumnSoftdrinksName extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->renameColumn('total_softDrinks', 'total_softdrinks');
        });

        Schema::table('states', function (Blueprint $table) {
            $table->renameColumn('total_softDrinks', 'total_softdrinks');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->renameColumn('total_softDrinks', 'total_softdrinks');
        });
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeAlcoholPlasticCupsName extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('alcohol', function (Blueprint $table) {
            $table->renameColumn('plastic_cups', 'alcohol_plastic_cups');
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

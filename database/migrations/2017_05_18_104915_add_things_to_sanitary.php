<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddThingsToSanitary extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sanitary', function (Blueprint $table) {
            $table->integer('ear_swabs')->unsigned()->nullable();
            $table->integer('tooth_pick')->unsigned()->nullable();
            $table->integer('tooth_brush')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sanitary', function (Blueprint $table) {
            $table->dropColumn('ear_swabs');
            $table->dropColumn('tooth_pick');
            $table->dropColumn('tooth_brush');
        });
    }
}

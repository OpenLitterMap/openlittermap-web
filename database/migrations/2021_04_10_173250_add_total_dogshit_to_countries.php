<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTotalDogshitToCountries extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->unsignedBigInteger('total_dogshit')->nullable();
        });

        Schema::table('states', function (Blueprint $table) {
            $table->unsignedBigInteger('total_dogshit')->nullable();
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->unsignedBigInteger('total_dogshit')->nullable();
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
            $table->dropColumn('total_dogshit');
        });

        Schema::table('states', function (Blueprint $table) {
            $table->dropColumn('total_dogshit');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('total_dogshit');
        });
    }
}

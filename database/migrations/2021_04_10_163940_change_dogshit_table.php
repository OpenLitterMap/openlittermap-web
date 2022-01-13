<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeDogshitTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dogshit', function (Blueprint $table) {
            $table->unsignedInteger('poo')->nullable();
            $table->unsignedInteger('poo_in_bag')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dogshit', function (Blueprint $table) {
            $table->dropColumn('poo');
            $table->dropColumn('poo_in_bag');
        });
    }
}

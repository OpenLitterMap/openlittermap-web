<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeDonateColumnNameToAmount extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('donates', function (Blueprint $table) {
            $table->renameColumn('one', 'amount');
            $table->dropColumn('two');
            $table->dropColumn('three');
            $table->dropColumn('four');
            $table->dropColumn('five');
            $table->dropColumn('six');
            $table->dropColumn('seven');
            $table->dropColumn('eight');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('donates', function (Blueprint $table) {
            //
        });
    }
}

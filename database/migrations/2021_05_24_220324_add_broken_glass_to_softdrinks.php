<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBrokenGlassToSoftdrinks extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('softdrinks', function (Blueprint $table) {
            $table->unsignedBigInteger('broken_glass')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('softdrinks', function (Blueprint $table) {
            $table->dropColumn('broken_glass');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGhostnetsToCoastal extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('coastal', function (Blueprint $table) {
            $table->unsignedInteger('ghost_nets')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('coastal', function (Blueprint $table) {
            $table->dropColumn('ghost_nets');
        });
    }
}

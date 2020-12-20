<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSettingsToEachTeam extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('team_user', function (Blueprint $table) {
            $table->boolean('show_name_maps')->default(false);
            $table->boolean('show_username_maps')->default(false);
            $table->boolean('show_name_leaderboards')->default(false);
            $table->boolean('show_username_leaderboards')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('team_user', function (Blueprint $table) {
            $table->dropColumn('show_name_maps');
            $table->dropColumn('show_username_maps');
            $table->dropColumn('show_name_leaderboards');
            $table->dropColumn('show_username_leaderboards');
        });
    }
}

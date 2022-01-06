<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTeamClustersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('team_clusters', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('team_id');
            $table->integer('zoom');
            $table->double('lat');
            $table->double('lon');
            $table->unsignedBigInteger('point_count');
            $table->string('point_count_abbreviated');
            $table->string('geohash');
            $table->timestamps();

            $table->foreign('team_id')
                ->references('id')
                ->on('teams');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('team_clusters');
    }
}

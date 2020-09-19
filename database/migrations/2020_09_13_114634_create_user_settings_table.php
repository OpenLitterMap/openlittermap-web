<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    // public function up()
    // {
    //     Schema::create('user_settings', function (Blueprint $table) {
    //         $table->id();
    //         $table->integer('user_id');
    //         $table->foreign('user_id')->references('id')->on('users');

    //         $table->boolean('picked_up')->default(0);
    //         $table->string('global_flag')->nullable();
    //         $table->boolean('previous_tags')->default(0);

    //         $table->boolean('litter_picked_up')->default(0);
    //         $table->boolean('show_name_maps')->default(0);
    //         $table->boolean('show_username_maps')->default(0);
    //         $table->boolean('show_name_leaderboard')->default(0);
    //         $table->boolean('show_username_leaderboard')->default(0);
    //         $table->boolean('show_name_createdby')->default(0);
    //         $table->boolean('show_username_createdby')->default(0);
    //         $table->timestamps();
    //     });
    // }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_settings');
    }
}

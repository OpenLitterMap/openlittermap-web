<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHandSanitizersToSanitary extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sanitary', function (Blueprint $table) {
            $table->integer('hand_sanitiser')->nullable();
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
            $table->dropColumn('hand_sanitiser');
        });
    }
}

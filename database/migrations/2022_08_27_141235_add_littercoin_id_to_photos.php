<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLittercoinIdToPhotos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->unsignedBigInteger('littercoin_id')->nullable();

            $table->foreign('littercoin_id')->references('id')->on('littercoins');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropForeign(['littercoin_id']);

            $table->dropColumn('littercoin_id');
        });
    }
}

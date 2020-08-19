<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEvenMoreCascadeDeleteToPhotos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('photos', function (Blueprint $table) {
            // $table->dropForeign('photos_sanitary_id_foreign');
            // $table->foreign('sanitary_id')->references('id')->on('sanitary')->onDelete('cascade');

            // $table->dropForeign('photos_soft_drinks_id_foreign');
            // $table->foreign('soft_drinks_id')->references('id')->on('soft_drinks')->onDelete('cascade');
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
            //
        });
    }
}

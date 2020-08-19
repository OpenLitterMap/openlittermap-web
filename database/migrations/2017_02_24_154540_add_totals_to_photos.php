<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTotalsToPhotos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->integer('smoking_id')->unsigned()->nullable();
            $table->foreign('smoking_id')->references('id')->on('smoking')->onDelete('cascade');

            $table->integer('food_id')->unsigned()->nullable();
            $table->foreign('food_id')->references('id')->on('food')->onDelete('cascade');

            $table->integer('coffee_id')->unsigned()->nullable();
            $table->foreign('coffee_id')->references('id')->on('coffee')->onDelete('cascade');

            $table->integer('alcohol_id')->unsigned()->nullable();
            $table->foreign('alcohol_id')->references('id')->on('alcohol')->onDelete('cascade');

            $table->integer('softdrinks_id')->unsigned()->nullable();
            $table->foreign('softdrinks_id')->references('id')->on('soft_drinks')->onDelete('cascade');

            $table->integer('drugs_id')->unsigned()->nullable();
            $table->foreign('drugs_id')->references('id')->on('drugs')->onDelete('cascade');

            $table->integer('sanitary_id')->unsigned()->nullable();
            $table->foreign('sanitary_id')->references('id')->on('sanitary')->onDelete('cascade');

            $table->integer('other_id')->unsigned()->nullable();
            $table->foreign('other_id')->references('id')->on('others')->onDelete('cascade');
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

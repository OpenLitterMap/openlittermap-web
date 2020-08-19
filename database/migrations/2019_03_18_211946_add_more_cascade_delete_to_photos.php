<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMoreCascadeDeleteToPhotos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('photos', function (Blueprint $table) {
            // $table->dropForeign('photos_drugs_id_foreign');
            // $table->foreign('drugs_id')->references('id')->on('drugs')->onDelete('cascade');

            // $table->dropForeign('photos_art_id_foreign');
            // $table->foreign('art_id')->references('id')->on('arts')->onDelete('cascade');

            // $table->dropForeign('photos_brands_id_foreign');
            // $table->foreign('brands_id')->references('id')->on('brands')->onDelete('cascade');

            // $table->dropForeign('photos_trashdog_id_foreign');
            // $table->foreign('trashdog_id')->references('id')->on('trash_dogs')->onDelete('cascade');

            // $table->dropForeign('photos_smoking_id_foreign');
            // $table->foreign('smoking_id')->references('id')->on('smoking')->onDelete('cascade');

            // $table->dropForeign('photos_alcohol_id_foreign');
            // $table->foreign('alcohol_id')->references('id')->on('alcohol')->onDelete('cascade');

            // $table->dropForeign('photos_coastal_id_foreign');
            // $table->foreign('coastal_id')->references('id')->on('coastal')->onDelete('cascade');

            // $table->dropForeign('photos_coffee_id_foreign');
            // $table->foreign('coffee_id')->references('id')->on('coffee')->onDelete('cascade');

            // $table->dropForeign('photos_food_id_foreign');
            // $table->foreign('food_id')->references('id')->on('food')->onDelete('cascade');

            // $table->dropForeign('photos_pathways_id_foreign');
            // $table->foreign('pathways_id')->references('id')->on('pathways')->onDelete('cascade');
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

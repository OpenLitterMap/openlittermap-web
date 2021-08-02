<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixPhotosForeignKeys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropForeign('photos_alcohol_id_foreign');
            $table->foreign('alcohol_id')->references('id')->on('alcohol')->nullOnDelete();

            $table->dropForeign('photos_art_id_foreign');
            $table->foreign('art_id')->references('id')->on('arts')->nullOnDelete();

            $table->dropForeign('photos_brands_id_foreign');
            $table->foreign('brands_id')->references('id')->on('brands')->nullOnDelete();

            $table->dropForeign('photos_softdrinks_id_foreign');
            $table->foreign('softdrinks_id')->references('id')->on('softdrinks')->nullOnDelete();

            $table->dropForeign('photos_smoking_id_foreign');
            $table->foreign('smoking_id')->references('id')->on('smoking')->nullOnDelete();

            $table->dropForeign('photos_sanitary_id_foreign');
            $table->foreign('sanitary_id')->references('id')->on('sanitary')->nullOnDelete();

            $table->dropForeign('photos_political_id_foreign');
            $table->foreign('political_id')->references('id')->on('politicals')->nullOnDelete();

            $table->dropForeign('photos_pathways_id_foreign');
            $table->foreign('pathways_id')->references('id')->on('pathways')->nullOnDelete();

            $table->dropForeign('photos_other_id_foreign');
            $table->foreign('other_id')->references('id')->on('other')->nullOnDelete();

            $table->dropForeign('photos_pathways_id_foreign');
            $table->foreign('pathways_id')->references('id')->on('pathways')->nullOnDelete();

            $table->dropForeign('photos_other_id_foreign');
            $table->foreign('other_id')->references('id')->on('other')->nullOnDelete();

            $table->dropForeign('photos_food_id_foreign');
            $table->foreign('food_id')->references('id')->on('food')->nullOnDelete();

            $table->dropForeign('photos_drugs_id_foreign');
            $table->foreign('drugs_id')->references('id')->on('drugs')->nullOnDelete();

            $table->dropForeign('photos_dumping_id_foreign');
            $table->foreign('dumping_id')->references('id')->on('dumping')->nullOnDelete();

            $table->dropForeign('photos_industrial_id_foreign');
            $table->foreign('industrial_id')->references('id')->on('industrial')->nullOnDelete();

            $table->dropForeign('photos_trashdog_id_foreign');
            $table->foreign('trashdog_id')->references('id')->on('trashdog')->nullOnDelete();

            $table->dropForeign('photos_coffee_id_foreign');
            $table->foreign('coffee_id')->references('id')->on('coffee')->nullOnDelete();

            $table->dropForeign('photos_coastal_id_foreign');
            $table->foreign('coastal_id')->references('id')->on('coastal')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}

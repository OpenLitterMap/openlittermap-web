<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewTagsAndClearExistingOnes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Photos and categories + tags
        Schema::table('photos', function (Blueprint $table) {
            $table->dropForeign('photos_smoking_id_foreign');
            $table->dropColumn('smoking_id');
            $table->dropForeign('photos_brands_id_foreign');
            $table->dropColumn('brands_id');
            $table->dropForeign('photos_other_id_foreign');
            $table->dropColumn('other_id');
            $table->dropForeign('photos_trashdog_id_foreign');
            $table->dropColumn('trashdog_id');
            $table->dropForeign('photos_alcohol_id_foreign');
            $table->dropColumn('alcohol_id');
            $table->dropForeign('photos_art_id_foreign');
            $table->dropColumn('art_id');
            $table->dropForeign('photos_coastal_id_foreign');
            $table->dropColumn('coastal_id');
            $table->dropForeign('photos_coffee_id_foreign');
            $table->dropColumn('coffee_id');
            $table->dropColumn('dogshit_id');
            $table->dropForeign('photos_drugs_id_foreign');
            $table->dropColumn('drugs_id');
            $table->dropForeign('photos_dumping_id_foreign');
            $table->dropColumn('dumping_id');
            $table->dropForeign('photos_food_id_foreign');
            $table->dropColumn('food_id');
            $table->dropForeign('photos_industrial_id_foreign');
            $table->dropColumn('industrial_id');
            $table->dropForeign('photos_pathways_id_foreign');
            $table->dropColumn('pathways_id');
            $table->dropForeign('photos_political_id_foreign');
            $table->dropColumn('political_id');
            $table->dropForeign('photos_sanitary_id_foreign');
            $table->dropColumn('sanitary_id');
            $table->dropForeign('photos_softdrinks_id_foreign');
            $table->dropColumn('softdrinks_id');
        });

        Schema::dropIfExists('smoking');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('other');
        Schema::dropIfExists('trashdog');
        Schema::dropIfExists('alcohol');
        Schema::dropIfExists('arts');
        Schema::dropIfExists('coastal');
        Schema::dropIfExists('coffee');
        Schema::dropIfExists('dogshit');
        Schema::dropIfExists('drugs');
        Schema::dropIfExists('dumping');
        Schema::dropIfExists('food');
        Schema::dropIfExists('industrial');
        Schema::dropIfExists('pathways');
        Schema::dropIfExists('politicals');
        Schema::dropIfExists('sanitary');
        Schema::dropIfExists('softdrinks');
        Schema::dropIfExists('farming');


        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id');
            $table->string('name');
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories');
        });

        Schema::create('photo_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('photo_id');
            $table->foreignId('tag_id');
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->foreign('photo_id')->references('id')->on('photos');
            $table->foreign('tag_id')->references('id')->on('tags');
        });

//        Schema::create('ordnance', function (Blueprint $table) {
//            $table->increments('id');
//            $table->integer('land_mine')->unsigned()->nullable();
//            $table->integer('missile')->unsigned()->nullable();
//            $table->integer('grenade')->unsigned()->nullable();
//            $table->integer('shell')->unsigned()->nullable();
//            $table->integer('other')->unsigned()->nullable();
//            $table->timestamps();
//        });
//        Schema::create('military_equipment_remnant', function (Blueprint $table) {
//            $table->increments('id');
//            $table->integer('metal_debris')->unsigned()->nullable();
//            $table->integer('armoured_vehicle')->unsigned()->nullable();
//            $table->integer('weapon')->unsigned()->nullable();
//            $table->timestamps();
//        });
//
//        Schema::table('photos', function (Blueprint $table) {
//            $table->unsignedInteger('ordnance_id')->nullable();
//            $table->foreign('ordnance_id')->references('id')->on('ordnance')->nullOnDelete();
//            $table->unsignedInteger('military_equipment_remnant_id')->nullable();
//            $table->foreign('military_equipment_remnant_id')->references('id')->on('military_equipment_remnant')->nullOnDelete();
//        });

        // Other tables
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('bbox_verification_count');
            $table->dropColumn('littercoin_owed');
            $table->dropColumn('littercoin_allowance');
            $table->dropColumn('littercoin_instructions_received');
            $table->dropColumn('count_correctly_verified');
        });
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('littercoin_paid');
            $table->dropColumn('littercoin_issued');
        });
        Schema::table('states', function (Blueprint $table) {
            $table->dropColumn('littercoin_paid');
            $table->dropColumn('littercoin_issued');
        });
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('littercoin_paid');
            $table->dropColumn('littercoin_issued');
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

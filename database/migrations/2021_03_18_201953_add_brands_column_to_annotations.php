<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBrandsColumnToAnnotations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('annotations', function (Blueprint $table) {
            $table->string('category')->nullable();
            $table->string('tag')->nullable();
            $table->unsignedInteger('tag_id')->nullable();
            $table->string('brand')->nullable();
            $table->unsignedInteger('brand_id')->nullable();
            $table->unsignedInteger('added_by')->nullable();
            $table->unsignedInteger('verified_by')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('annotations', function (Blueprint $table) {
            $table->dropColumn(['category', 'tag', 'tag_id', 'brand', 'brand_id', 'added_by', 'verified_by']);
        });
    }
}

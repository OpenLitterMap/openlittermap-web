<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMaterialTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('material', function (Blueprint $table) {
            $table->id();
            $table->integer('aluminium')->unsigned()->nullable();
            $table->integer('bronze')->unsigned()->nullable();
            $table->integer('carbon_fiber')->unsigned()->nullable();
            $table->integer('ceramic')->unsigned()->nullable();
            $table->integer('composite')->unsigned()->nullable();
            $table->integer('concrete')->unsigned()->nullable();
            $table->integer('copper')->unsigned()->nullable();
            $table->integer('fiberglass')->unsigned()->nullable();
            $table->integer('glass')->unsigned()->nullable();
            $table->integer('iron_or_steel')->unsigned()->nullable();
            $table->integer('latex')->unsigned()->nullable();
            $table->integer('metal')->unsigned()->nullable();
            $table->integer('nickel')->unsigned()->nullable();
            $table->integer('nylon')->unsigned()->nullable();
            $table->integer('paper')->unsigned()->nullable();
            $table->integer('plastic')->unsigned()->nullable();
            $table->integer('polyethylene')->unsigned()->nullable();
            $table->integer('polymer')->unsigned()->nullable();
            $table->integer('polypropylene')->unsigned()->nullable();
            $table->integer('polystyrene')->unsigned()->nullable();
            $table->integer('pvc')->unsigned()->nullable();
            $table->integer('rubber')->unsigned()->nullable();
            $table->integer('titanium')->unsigned()->nullable();
            $table->integer('wood')->unsigned()->nullable();

            $table->timestamps();
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->unsignedBigInteger('material_id')->nullable();
            $table->foreign('material_id')->references('id')->on('material');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('material');
    }
}

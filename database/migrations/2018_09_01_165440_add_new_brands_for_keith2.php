<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNewBrandsForKeith2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->integer('asahi')->unsigned()->nullable();
            $table->integer('aldi')->unsigned()->nullable();
            $table->integer('ballygowan')->unsigned()->nullable();
            $table->integer('bulmers')->unsigned()->nullable();
            $table->integer('burgerking')->unsigned()->nullable();
            $table->integer('cadburys')->unsigned()->nullable();
            $table->integer('carlsberg')->unsigned()->nullable();
            $table->integer('coles')->unsigned()->nullable();
            $table->integer('circlek')->unsigned()->nullable();
            $table->integer('dunnes')->unsigned()->nullable();
            $table->integer('doritos')->unsigned()->nullable();
            $table->integer('drpepper')->unsigned()->nullable();
            $table->integer('duracell')->unsigned()->nullable();
            $table->integer('durex')->unsigned()->nullable();
            $table->integer('evian')->unsigned()->nullable();
            $table->integer('fosters')->unsigned()->nullable();
            $table->integer('gatorade')->unsigned()->nullable();
            $table->integer('guinness')->unsigned()->nullable();
            $table->integer('haribo')->unsigned()->nullable();
            $table->integer('kfc')->unsigned()->nullable();
            $table->integer('lidl')->unsigned()->nullable();
            $table->integer('lindenvillage')->unsigned()->nullable();
            $table->integer('lucozade')->unsigned()->nullable();
            $table->integer('nero')->unsigned()->nullable();
            $table->integer('mars')->unsigned()->nullable();
            $table->integer('powerade')->unsigned()->nullable();
            $table->integer('ribena')->unsigned()->nullable();
            $table->integer('sainsburys')->unsigned()->nullable();
            $table->integer('spar')->unsigned()->nullable();
            $table->integer('stella')->unsigned()->nullable();
            $table->integer('supervalu')->unsigned()->nullable();
            $table->integer('tesco')->unsigned()->nullable();
            $table->integer('thins')->unsigned()->nullable();
            $table->integer('volvic')->unsigned()->nullable();
            $table->integer('waitrose')->unsigned()->nullable();
            $table->integer('walkers')->unsigned()->nullable();
            $table->integer('woolworths')->unsigned()->nullable();
            $table->integer('wrigleys')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brands', function (Blueprint $table) {
            //
        });
    }
}

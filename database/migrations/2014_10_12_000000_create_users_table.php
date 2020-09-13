<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->boolean('verified')->default(false);
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->unique();
            $table->string('avatar')->default('default.jpg');
            $table->string('password');
            $table->string('token')->nullable();

            $table->boolean('show_name')->default(0);
            $table->boolean('show_username')->default(0);
            $table->string('items_remaining')->default(1);

            $table->integer('role_id')->index()->unsigned()->nullable();

            $table->string('billing_id')->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->integer('xp')->unsigned()->default('0');
            $table->integer('level')->unsigned()->default('0');

            $table->integer('total_images')->unsigned()->nullable();
            $table->integer('total_smoking')->unsigned()->nullable();
            $table->integer('total_cigaretteButts')->unsigned()->nullable();
            $table->integer('total_food')->unsigned()->nullable();
            $table->integer('total_softDrinks')->unsigned()->nullable();
            $table->integer('total_plasticBottles')->unsigned()->nullable();
            $table->integer('total_alcohol')->unsigned()->nullable();
            $table->integer('total_coffee')->unsigned()->nullable();
            // $table->integer('total_drugs')->unsigned()->nullable();
            // $table->integer('total_needles')->unsigned()->nullable();
            $table->integer('total_sanitary')->unsigned()->nullable();
            $table->integer('total_other')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}

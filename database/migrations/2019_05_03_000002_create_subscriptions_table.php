<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscriptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id')->change();
            $table->unsignedBigInteger('user_id')->change();
//            $table->string('name');
//            $table->string('stripe_id');
            $table->string('stripe_status');
            $table->string('stripe_plan')->nullable()->change();
            $table->integer('quantity')->nullable()->change();
//            $table->timestamp('trial_ends_at')->nullable();
//            $table->timestamp('ends_at')->nullable();
//            $table->timestamps();
            $table->index(['user_id', 'stripe_status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscriptions');
    }
}

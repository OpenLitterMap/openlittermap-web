<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop 11 deprecated/dead tables with zero active code references.
 *
 * See readme/audit/MiscellaneousTableAudit.md for full grep evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        // suburbs has FKs to countries/states/cities — drop those first
        Schema::dropIfExists('suburbs');

        Schema::dropIfExists('awards');
        Schema::dropIfExists('global_levels');
        Schema::dropIfExists('levels');
        Schema::dropIfExists('experience');
        Schema::dropIfExists('farming');
        Schema::dropIfExists('firewall');
        Schema::dropIfExists('halls');
        Schema::dropIfExists('donates');
        Schema::dropIfExists('websockets_statistics_entries');
        Schema::dropIfExists('email_subscriptions');
    }

    public function down(): void
    {
        Schema::create('awards', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->string('item');
            $table->unsignedInteger('quantity')->default(0);
            $table->string('reward');
            $table->timestamps();
        });

        Schema::create('global_levels', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('xp')->nullable();
            $table->timestamps();
        });

        Schema::create('levels', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('xp');
            $table->integer('level');
            $table->timestamps();
        });

        Schema::create('suburbs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('suburb');
            $table->unsignedInteger('needles')->default(0);
            $table->timestamps();
            $table->unsignedInteger('country_id')->nullable();
            $table->unsignedInteger('state_id')->nullable();
            $table->unsignedInteger('city_id')->nullable();

            $table->foreign('country_id')->references('id')->on('countries');
            $table->foreign('state_id')->references('id')->on('states');
            $table->foreign('city_id')->references('id')->on('cities');
        });

        Schema::create('experience', function (Blueprint $table) {
            $table->integer('xp');
            $table->integer('level');
        });

        Schema::create('farming', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('plastic')->nullable();
            $table->timestamps();
        });

        Schema::create('firewall', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ip_address', 39)->unique();
            $table->boolean('whitelisted')->default(false);
            $table->timestamps();
        });

        Schema::create('halls', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->unsignedInteger('user_id');
            $table->timestamps();
        });

        Schema::create('donates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('amount');
            $table->timestamps();
        });

        Schema::create('websockets_statistics_entries', function (Blueprint $table) {
            $table->increments('id');
            $table->string('app_id');
            $table->integer('peak_connection_count');
            $table->integer('websocket_message_count');
            $table->integer('api_message_count');
            $table->timestamps();
        });

        Schema::create('email_subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email')->default('')->unique();
            $table->timestamps();
        });
    }
};

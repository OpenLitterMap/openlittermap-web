<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserIdToCountries extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->unsignedInteger('user_id_last_uploaded')->nullable()->after('updated_at');
            $table->foreign('user_id_last_uploaded')->references('id')->on('users');
        });

        Schema::table('states', function (Blueprint $table) {
            $table->unsignedInteger('user_id_last_uploaded')->nullable()->after('updated_at');
            $table->foreign('user_id_last_uploaded')->references('id')->on('users');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->unsignedInteger('user_id_last_uploaded')->nullable()->after('updated_at');
            $table->foreign('user_id_last_uploaded')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropForeign(['user_id_last_uploaded']);
            $table->dropColumn('user_id_last_uploaded');
        });

        Schema::table('states', function (Blueprint $table) {
            $table->dropForeign(['user_id_last_uploaded']);
            $table->dropColumn('user_id_last_uploaded');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropForeign(['user_id_last_uploaded']);
            $table->dropColumn('user_id_last_uploaded');
        });
    }
}

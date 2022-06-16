<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPreventOthersTaggingColumnToUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('prevent_others_tagging_my_photos')
                ->nullable()
                ->default(false)
                ->after('verification_required')
                ->index();
        });
    }
}

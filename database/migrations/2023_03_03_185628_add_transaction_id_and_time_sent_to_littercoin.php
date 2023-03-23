<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTransactionIdAndTimeSentToLittercoin extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('littercoins', function (Blueprint $table) {
            $table->text('transaction_id')->nullable()->after('photo_id');
            $table->string('timestamp')->nullable()->after('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('littercoins', function (Blueprint $table) {
            $table->dropColumn([
                'transaction_id',
                'timestamp'
            ]);
        });
    }
}

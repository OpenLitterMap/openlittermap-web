<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->string('sub_token', 30)->nullable()->unique()->after('email');
        });

        // Backfill tokens for existing subscribers
        DB::table('subscribers')->whereNull('sub_token')->eachById(function ($subscriber) {
            DB::table('subscribers')
                ->where('id', $subscriber->id)
                ->update(['sub_token' => Str::random(30)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn('sub_token');
        });
    }
};

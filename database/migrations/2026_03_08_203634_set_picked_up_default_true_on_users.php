<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New users default to picked_up = true, but column stays nullable
        // (null = user hasn't set a preference yet)
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('picked_up')->default(true)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('picked_up')->default(null)->nullable()->change();
        });
    }
};

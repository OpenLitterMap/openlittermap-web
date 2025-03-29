<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('litter_objects', function (Blueprint $table) {
            $table->boolean('is_custom')->default(false);
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->boolean('is_custom')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('litter_objects', function (Blueprint $table) {
            $table->dropColumn('is_custom');
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('is_custom');
        });
    }
};

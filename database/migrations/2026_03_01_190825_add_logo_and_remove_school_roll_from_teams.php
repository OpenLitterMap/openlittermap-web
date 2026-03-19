<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('county');
            $table->unsignedInteger('max_participants')->nullable()->after('logo');
            $table->dropColumn('school_roll_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['logo', 'max_participants']);
            $table->string('school_roll_number', 50)->nullable()->after('county');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make state_id nullable so photos can be uploaded even when
     * geocoding returns incomplete location data (e.g., no state).
     */
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->unsignedInteger('state_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->unsignedInteger('state_id')->nullable(false)->change();
        });
    }
};

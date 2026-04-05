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
        Schema::table('user_quick_tags', function (Blueprint $table) {
            $table->string('custom_name', 60)->nullable()->default(null)->after('type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_quick_tags', function (Blueprint $table) {
            $table->dropColumn('custom_name');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_litter_object', function (Blueprint $table) {
            // - Historical CLOs resolve saved observations but are hidden from new choices.
            $table->boolean('is_selectable')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('category_litter_object', fn (Blueprint $table) => $table->dropColumn('is_selectable'));
    }
};

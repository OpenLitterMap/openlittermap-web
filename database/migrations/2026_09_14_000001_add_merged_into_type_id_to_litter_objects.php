<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('litter_objects', function (Blueprint $table) {
            $table->foreignId('merged_into_type_id')->nullable()
                ->constrained('litter_object_types')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('litter_objects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_type_id');
        });
    }
};

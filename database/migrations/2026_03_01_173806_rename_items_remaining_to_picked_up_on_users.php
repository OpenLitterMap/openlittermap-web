<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename items_remaining → picked_up and flip values.
     *
     * items_remaining was an inverted boolean:
     *   items_remaining = false  →  picked_up = true
     *   items_remaining = true   →  picked_up = false
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('items_remaining', 'picked_up');
        });

        // Flip all values: the old column was inverted
        DB::statement('UPDATE users SET picked_up = NOT picked_up');

        // Update default: items_remaining defaulted to false (= picked_up true)
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('picked_up')->default(true)->change();
        });
    }

    public function down(): void
    {
        // Flip values back before renaming
        DB::statement('UPDATE users SET picked_up = NOT picked_up');

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('picked_up', 'items_remaining');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix Spatie polymorphic model_type from singular to plural namespace.
     *
     * DB has: App\Models\User\User (singular)
     * Actual: App\Models\Users\User (plural)
     */
    public function up(): void
    {
        $old = 'App\\Models\\User\\User';
        $new = 'App\\Models\\Users\\User';

        DB::table('model_has_roles')->where('model_type', $old)->update(['model_type' => $new]);
        DB::table('model_has_permissions')->where('model_type', $old)->update(['model_type' => $new]);

        // Also fix any ancient App\User entries from Laravel's original default
        DB::table('model_has_roles')->where('model_type', 'App\\User')->update(['model_type' => $new]);
        DB::table('model_has_permissions')->where('model_type', 'App\\User')->update(['model_type' => $new]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $old = 'App\\Models\\User\\User';
        $new = 'App\\Models\\Users\\User';

        DB::table('model_has_roles')->where('model_type', $new)->update(['model_type' => $old]);
        DB::table('model_has_permissions')->where('model_type', $new)->update(['model_type' => $old]);
    }
};

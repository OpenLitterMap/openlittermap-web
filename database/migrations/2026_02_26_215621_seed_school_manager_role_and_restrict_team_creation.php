<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Seed the school_manager role with permissions and restrict team
     * creation to school managers only (remaining_teams default 0).
     */
    public function up(): void
    {
        // Clear Spatie's permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create role (idempotent)
        $role = Role::firstOrCreate(
            ['name' => 'school_manager', 'guard_name' => 'web']
        );

        // Create permissions (idempotent)
        $permissions = [
            'create school team',
            'manage school team',
            'toggle safeguarding',
            'view student identities',
        ];

        foreach ($permissions as $permName) {
            $perm = Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web']
            );
            if (! $role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
            }
        }

        // Restrict team creation: default 0 for new users, only school managers get 1
        DB::statement("ALTER TABLE users ALTER COLUMN remaining_teams SET DEFAULT 0");

        // Set all non-school-manager users to 0
        DB::table('users')
            ->whereNotIn('id', function ($query) {
                $query->select('model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', 'school_manager')
                    ->where('model_has_roles.model_type', 'App\\Models\\Users\\User');
            })
            ->update(['remaining_teams' => 0]);

        // Set school managers to 1 (if they haven't already used their quota)
        DB::table('users')
            ->whereIn('id', function ($query) {
                $query->select('model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', 'school_manager')
                    ->where('model_has_roles.model_type', 'App\\Models\\Users\\User');
            })
            ->where('remaining_teams', '>', 1)
            ->update(['remaining_teams' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users ALTER COLUMN remaining_teams SET DEFAULT 10");
    }
};

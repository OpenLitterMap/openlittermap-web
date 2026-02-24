<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        // Clear Spatie cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions (web guard — matches User model default)
        Permission::create(['name' => 'create school team', 'guard_name' => 'web']);
        Permission::create(['name' => 'manage school team', 'guard_name' => 'web']);
        Permission::create(['name' => 'toggle safeguarding', 'guard_name' => 'web']);
        Permission::create(['name' => 'view student identities', 'guard_name' => 'web']);

        // Create role with all school permissions
        $role = Role::create(['name' => 'school_manager', 'guard_name' => 'web']);
        $role->givePermissionTo([
            'create school team',
            'manage school team',
            'toggle safeguarding',
            'view student identities',
        ]);
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::where('name', 'school_manager')->delete();
        Permission::whereIn('name', [
            'create school team',
            'manage school team',
            'toggle safeguarding',
            'view student identities',
        ])->delete();
    }
};

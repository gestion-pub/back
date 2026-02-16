<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $entities = ['planning', 'conducteur', 'client', 'compagne', 'category'];
        $actions = ['create', 'read', 'update', 'delete'];

        $allPermissions = [];

        foreach ($entities as $entity) {
            foreach ($actions as $action) {
                $slug = "{$action}_{$entity}";
                $name = ucfirst($action) . " " . ucfirst($entity);

                $allPermissions[] = \App\Models\Permission::updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $name]
                );
            }
        }

        // Create administrative permissions
        $adminPermissions = ['manage_roles', 'manage_users'];
        foreach ($adminPermissions as $slug) {
            $name = ucwords(str_replace('_', ' ', $slug));
            $allPermissions[] = \App\Models\Permission::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name]
            );
        }

        // Create Default Roles
        $admin = \App\Models\Role::updateOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrateur']
        );

        $commercial = \App\Models\Role::updateOrCreate(
            ['slug' => 'commercial'],
            ['name' => 'Commercial']
        );

        // Assign All Permissions to Admin
        $admin->permissions()->sync(collect($allPermissions)->pluck('id'));

        // Assign subset to Commercial
        $commercialPermissions = \App\Models\Permission::where('slug', 'like', 'read_%')
            ->orWhere('slug', 'like', 'create_planning')
            ->pluck('id');
        $commercial->permissions()->sync($commercialPermissions);

        // Assign Admin role to the first user (for testing/access)
        $user = \App\Models\User::first();
        if ($user) {
            $user->roles()->sync([$admin->id]);
            $user->update(['role' => 'admin']);
        }
    }
}

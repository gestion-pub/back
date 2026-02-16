<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Role;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Fix existing 'administrateur' roles in the roles table if they exist
        $adminRole = Role::where('slug', 'administrateur')->first();
        if ($adminRole) {
            $adminRole->update(['slug' => 'admin']);
        } else {
            // Ensure admin role exists
            $adminRole = Role::firstOrCreate(
                ['slug' => 'admin'],
                ['name' => 'Administrateur']
            );
        }

        // 2. Fetch all users and link them to roles based on their 'role' column
        $users = User::all();
        foreach ($users as $user) {
            $roleSlug = $user->role;
            if ($roleSlug) {
                $role = Role::where('slug', $roleSlug)->first();
                if ($role) {
                    // Use syncWithoutDetaching to avoid duplicates if already partially synced
                    $user->roles()->syncWithoutDetaching([$role->id]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // No simple way to reverse data sync without potentially losing intentional assignments
    }
};

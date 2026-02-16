<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        \App\Models\User::updateOrCreate(
            ['email' => 'admin@gestionpub.com'],
            [
                'name' => 'Admin User',
                'password' => \Illuminate\Support\Facades\Hash::make('admin'),
                'role' => 'admin'
            ]
        );

        $this->call(RolePermissionSeeder::class);
        $this->call(CampagneSeeder::class);
        $this->call(CategorieSeeder::class);
    }
}

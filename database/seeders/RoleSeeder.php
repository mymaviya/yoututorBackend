<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [

            [
                'name' => 'Admin',
                'slug' => 'admin',
            ],

            [
                'name' => 'Teacher',
                'slug' => 'teacher',
            ],

            [
                'name' => 'Reviewer',
                'slug' => 'reviewer',
            ],

            [
                'name' => 'Principal',
                'slug' => 'principal',
            ],
        ];

        foreach ($roles as $role) {

            Role::updateOrCreate(
                ['slug' => $role['slug']],
                ['name' => $role['name']]
            );
        }
    }
}

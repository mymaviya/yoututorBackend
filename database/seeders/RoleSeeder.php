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
                ['slug' => 'admin'],
                [
                    'name' => 'Admin',
                    'bypass_device_restriction' => true,
                ]
            );

            Role::updateOrCreate(
                ['slug' => 'teacher'],
                [
                    'name' => 'Teacher',
                    'bypass_device_restriction' => false,
                ]
            );

            Role::updateOrCreate(
                ['slug' => 'reviewer'],
                [
                    'name' => 'Reviewer',
                    'bypass_device_restriction' => false,
                ]
            );

            Role::updateOrCreate(
                ['slug' => 'examiner'],
                [
                    'name' => 'Examiner',
                    'bypass_device_restriction' => false,
                ]
            );
        }
    }
}

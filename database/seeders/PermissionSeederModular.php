<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PermissionSeederModular extends Seeder
{
    public function run(): void
    {
        $this->call([
            \Database\Seeders\Permissions\CorePermissionSeeder::class,
            \Database\Seeders\Permissions\AcademicPermissionSeeder::class,
            \Database\Seeders\Permissions\QuestionBankPermissionSeeder::class,
            \Database\Seeders\Permissions\PaperPermissionSeeder::class,
            \Database\Seeders\Permissions\TeacherPermissionSeeder::class,
            \Database\Seeders\Permissions\ExaminationPermissionSeeder::class,
            \Database\Seeders\Permissions\AdministrationPermissionSeeder::class,
            \Database\Seeders\Permissions\SecurityPermissionSeeder::class,
            \Database\Seeders\Permissions\ImportPermissionSeeder::class,
            \Database\Seeders\Permissions\SaaSPermissionSeeder::class,
        ]);
    }
}

<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;

class ImportPermissionSeeder extends Seeder
{
    use SeedsPermissions;

    public function run(): void
    {
        $permissions = [
            ['name' => 'Lesson Import', 'slug' => 'lessons.import', 'group_name' => 'Imports'],
            ['name' => 'Question Import', 'slug' => 'questions.import', 'group_name' => 'Imports'],
        ];

        $this->seedPermissions($permissions);
    }
}

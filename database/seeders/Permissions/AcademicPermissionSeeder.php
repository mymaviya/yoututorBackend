<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;

class AcademicPermissionSeeder extends Seeder
{
    use SeedsPermissions;

    public function run(): void
    {
        $permissions = [
            ['name' => 'View Grades', 'slug' => 'grades.view', 'group_name' => 'Academic'],
            ['name' => 'Create Grades', 'slug' => 'grades.create', 'group_name' => 'Academic'],
            ['name' => 'Edit Grades', 'slug' => 'grades.edit', 'group_name' => 'Academic'],
            ['name' => 'Delete Grades', 'slug' => 'grades.delete', 'group_name' => 'Academic'],
            ['name' => 'View Subjects', 'slug' => 'subjects.view', 'group_name' => 'Academic'],
            ['name' => 'Create Subjects', 'slug' => 'subjects.create', 'group_name' => 'Academic'],
            ['name' => 'Edit Subjects', 'slug' => 'subjects.edit', 'group_name' => 'Academic'],
            ['name' => 'Delete Subjects', 'slug' => 'subjects.delete', 'group_name' => 'Academic'],
            ['name' => 'View Lessons', 'slug' => 'lessons.view', 'group_name' => 'Academic'],
            ['name' => 'Create Lessons', 'slug' => 'lessons.create', 'group_name' => 'Academic'],
            ['name' => 'Edit Lessons', 'slug' => 'lessons.edit', 'group_name' => 'Academic'],
            ['name' => 'Delete Lessons', 'slug' => 'lessons.delete', 'group_name' => 'Academic'],
            ['name' => 'Manage Subject Templates', 'slug' => 'subjects.manage', 'group_name' => 'Academic'],
        ];

        $this->seedPermissions($permissions);
    }
}

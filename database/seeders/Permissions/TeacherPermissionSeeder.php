<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;

class TeacherPermissionSeeder extends Seeder
{
    use SeedsPermissions;

    public function run(): void
    {
        $permissions = [
            ['name' => 'View Teachers', 'slug' => 'teachers.view', 'group_name' => 'Teachers'],
            ['name' => 'Create Teachers', 'slug' => 'teachers.create', 'group_name' => 'Teachers'],
            ['name' => 'Edit Teachers', 'slug' => 'teachers.edit', 'group_name' => 'Teachers'],
            ['name' => 'Delete Teachers', 'slug' => 'teachers.delete', 'group_name' => 'Teachers'],
            ['name' => 'Import Teachers', 'slug' => 'teachers.import', 'group_name' => 'Teachers'],
            ['name' => 'View Teacher Tasks', 'slug' => 'teacher.tasks', 'group_name' => 'Teachers'],
            ['name' => 'Create Teacher Tasks', 'slug' => 'teacher.tasks.create', 'group_name' => 'Teachers'],
            ['name' => 'Edit Teacher Tasks', 'slug' => 'teacher.tasks.edit', 'group_name' => 'Teachers'],
            ['name' => 'Delete Teacher Tasks', 'slug' => 'teacher.tasks.delete', 'group_name' => 'Teachers'],
            ['name' => 'Teacher Dashboard', 'slug' => 'teacher.dashboard', 'group_name' => 'Teacher Portal'],
            ['name' => 'My Question Tasks', 'slug' => 'teacher.my.tasks', 'group_name' => 'Teacher Portal'],
            ['name' => 'My Exam Portions', 'slug' => 'teacher.exam.portions', 'group_name' => 'Teacher Portal'],
        ];

        $this->seedPermissions($permissions);
    }
}

<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;

class ExaminationPermissionSeeder extends Seeder
{
    use SeedsPermissions;

    public function run(): void
    {
        $permissions = [
            ['name' => 'View Exam Names', 'slug' => 'exam.names', 'group_name' => 'Examinations'],
            ['name' => 'Create Exam Names', 'slug' => 'exam.names.create', 'group_name' => 'Examinations'],
            ['name' => 'Edit Exam Names', 'slug' => 'exam.names.edit', 'group_name' => 'Examinations'],
            ['name' => 'Delete Exam Names', 'slug' => 'exam.names.delete', 'group_name' => 'Examinations'],
            ['name' => 'View Exam Portions', 'slug' => 'exam.portions', 'group_name' => 'Examinations'],
            ['name' => 'Create Exam Portions', 'slug' => 'exam.portions.create', 'group_name' => 'Examinations'],
            ['name' => 'Edit Exam Portions', 'slug' => 'exam.portions.edit', 'group_name' => 'Examinations'],
            ['name' => 'Delete Exam Portions', 'slug' => 'exam.portions.delete', 'group_name' => 'Examinations'],
        ];

        $this->seedPermissions($permissions);
    }
}

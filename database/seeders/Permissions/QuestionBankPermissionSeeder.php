<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;

class QuestionBankPermissionSeeder extends Seeder
{
    use SeedsPermissions;

    public function run(): void
    {
        $permissions = [
            ['name' => 'View Questions', 'slug' => 'questions.view', 'group_name' => 'Question Bank'],
            ['name' => 'Create Questions', 'slug' => 'questions.create', 'group_name' => 'Question Bank'],
            ['name' => 'Edit Questions', 'slug' => 'questions.edit', 'group_name' => 'Question Bank'],
            ['name' => 'Delete Questions', 'slug' => 'questions.delete', 'group_name' => 'Question Bank'],
            ['name' => 'Approve Questions', 'slug' => 'question.approvals', 'group_name' => 'Question Bank'],
            ['name' => 'Edit Language Questions', 'slug' => 'language.questions.edit', 'group_name' => 'Question Bank'],
            ['name' => 'View Question Types', 'slug' => 'question.types.view', 'group_name' => 'Question Bank'],
            ['name' => 'Create Question Types', 'slug' => 'question.types.create', 'group_name' => 'Question Bank'],
            ['name' => 'Edit Question Types', 'slug' => 'question.types.edit', 'group_name' => 'Question Bank'],
            ['name' => 'Delete Question Types', 'slug' => 'question.types.delete', 'group_name' => 'Question Bank'],
        ];

        $this->seedPermissions($permissions);
    }
}

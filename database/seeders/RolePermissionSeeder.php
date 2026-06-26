<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Super Admin / School Admin
        |--------------------------------------------------------------------------
        */

        $allPermissionIds = Permission::pluck('id');

        foreach (['superadmin', 'super_admin', 'admin'] as $roleSlug) {
            $role = Role::where('slug', $roleSlug)->first();

            if ($role) {
                $role->permissions()->sync($allPermissionIds);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Teacher
        |--------------------------------------------------------------------------
        | Uses current permission slugs from PermissionSeeder / SidebarMenuSeeder.
        */

        $this->syncRolePermissions('teacher', [
            'dashboard.view',
            'profile.view',
            'profile.update',

            'teacher.dashboard',
            'teacher.my.tasks',
            'teacher.exam.portions',

            'grades.view',
            'streams.view',
            'subjects.view',
            'lessons.view',

            'question.types.view',
            'questions.view',
            'questions.create',
            'questions.edit',
            'language.questions.edit',

            'papers.view',
            'papers.create',
            'papers.edit',
            'papers.generate',

            'exam.names',
            'exam.portions',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Reviewer
        |--------------------------------------------------------------------------
        */

        $this->syncRolePermissions('reviewer', [
            'dashboard.view',
            'profile.view',
            'questions.view',
            'question.approvals',
            'question.types.view',
            'grades.view',
            'subjects.view',
            'lessons.view',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Principal
        |--------------------------------------------------------------------------
        */

        $this->syncRolePermissions('principal', [
            'dashboard.view',
            'dashboard.analytics',
            'profile.view',

            'grades.view',
            'streams.view',
            'subjects.view',
            'lessons.view',

            'questions.view',
            'question.types.view',
            'papers.view',
            'paper.blueprints',

            'teachers.view',
            'teacher.tasks',
            'teacher.progress',
            'teacher.analytics',

            'exam.names',
            'exam.portions',
            'basic.reports',
        ]);
    }

    private function syncRolePermissions(string $roleSlug, array $permissionSlugs): void
    {
        $role = Role::where('slug', $roleSlug)->first();

        if (! $role) {
            return;
        }

        $permissionIds = Permission::whereIn('slug', $permissionSlugs)->pluck('id');

        $role->permissions()->sync($permissionIds);
    }
}

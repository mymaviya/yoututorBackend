<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ADMIN → ALL PERMISSIONS

        $admin = Role::where('slug', 'admin')->first();

        if ($admin) {

            $permissions = Permission::pluck('id');

            $admin->permissions()->sync($permissions);
        }

        // TEACHER PERMISSIONS

        $teacher = Role::where('slug', 'teacher')->first();

        if ($teacher) {

            $teacherPermissions = Permission::whereIn('slug', [
                'create_questions',
                'edit_questions',
                'view_question_bank',
                'generate_papers',
                'download_papers',
                'view_students',
            ])->pluck('id');

            $teacher->permissions()->sync($teacherPermissions);
        }

        // REVIEWER PERMISSIONS

        $reviewer = Role::where('slug', 'reviewer')->first();

        if ($reviewer) {

            $reviewerPermissions = Permission::whereIn('slug', [
                'approve_questions',
                'reject_questions',
                'view_question_bank',
            ])->pluck('id');

            $reviewer->permissions()->sync($reviewerPermissions);
        }

        // PRINCIPAL PERMISSIONS

        $principal = Role::where('slug', 'principal')->first();

        if ($principal) {

            $principalPermissions = Permission::whereIn('slug', [
                'view_dashboard',
                'view_analytics',
                'view_question_bank',
            ])->pluck('id');

            $principal->permissions()->sync($principalPermissions);
        }
    }
}

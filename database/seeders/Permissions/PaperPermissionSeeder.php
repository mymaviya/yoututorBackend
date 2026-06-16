<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;

class PaperPermissionSeeder extends Seeder
{
    use SeedsPermissions;

    public function run(): void
    {
        $permissions = [
            ['name' => 'View Papers', 'slug' => 'papers.view', 'group_name' => 'Papers'],
            ['name' => 'Create Papers', 'slug' => 'papers.create', 'group_name' => 'Papers'],
            ['name' => 'Edit Papers', 'slug' => 'papers.edit', 'group_name' => 'Papers'],
            ['name' => 'Delete Papers', 'slug' => 'papers.delete', 'group_name' => 'Papers'],
            ['name' => 'Generate Papers', 'slug' => 'papers.generate', 'group_name' => 'Papers'],
            ['name' => 'Export Papers', 'slug' => 'papers.export', 'group_name' => 'Papers'],
            ['name' => 'Print Papers', 'slug' => 'papers.print', 'group_name' => 'Papers'],
            ['name' => 'View Paper Blueprints', 'slug' => 'paper.blueprints', 'group_name' => 'Papers'],
            ['name' => 'Create Paper Blueprints', 'slug' => 'paper.blueprints.create', 'group_name' => 'Papers'],
            ['name' => 'Edit Paper Blueprints', 'slug' => 'paper.blueprints.edit', 'group_name' => 'Papers'],
            ['name' => 'Delete Paper Blueprints', 'slug' => 'paper.blueprints.delete', 'group_name' => 'Papers'],
        ];

        $this->seedPermissions($permissions);
    }
}

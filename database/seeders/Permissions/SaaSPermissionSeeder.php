<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;

class SaaSPermissionSeeder extends Seeder
{
    use SeedsPermissions;

    public function run(): void
    {
        $permissions = [
            ['name' => 'Manage Website Settings', 'slug' => 'settings.manage', 'group_name' => 'Website'],
            ['name' => 'View CMS Pages', 'slug' => 'cms.pages.view', 'group_name' => 'Website'],
            ['name' => 'Create CMS Pages', 'slug' => 'cms.pages.create', 'group_name' => 'Website'],
            ['name' => 'Edit CMS Pages', 'slug' => 'cms.pages.edit', 'group_name' => 'Website'],
            ['name' => 'Delete CMS Pages', 'slug' => 'cms.pages.delete', 'group_name' => 'Website'],
            ['name' => 'View Demo Enquiries', 'slug' => 'demo.enquiries.view', 'group_name' => 'SaaS CRM'],
            ['name' => 'Manage Demo Enquiries', 'slug' => 'demo.enquiries.manage', 'group_name' => 'SaaS CRM'],
            ['name' => 'View Subscriptions', 'slug' => 'subscriptions.view', 'group_name' => 'Subscriptions'],
            ['name' => 'Manage Subscriptions', 'slug' => 'subscriptions.manage', 'group_name' => 'Subscriptions'],
            ['name' => 'Extend Subscriptions', 'slug' => 'subscriptions.extend', 'group_name' => 'Subscriptions'],
            ['name' => 'Activate Subscriptions', 'slug' => 'subscriptions.activate', 'group_name' => 'Subscriptions'],
            ['name' => 'View Licenses', 'slug' => 'licenses.view', 'group_name' => 'Subscriptions'],
            ['name' => 'Manage Licenses', 'slug' => 'licenses.manage', 'group_name' => 'Subscriptions'],
            ['name' => 'View Payments', 'slug' => 'payments.view', 'group_name' => 'Payments'],
            ['name' => 'Manage Payments', 'slug' => 'payments.manage', 'group_name' => 'Payments'],
            ['name' => 'Issue Refunds', 'slug' => 'payments.refunds', 'group_name' => 'Payments'],
            ['name' => 'View SaaS Dashboard', 'slug' => 'saas.dashboard', 'group_name' => 'SaaS Dashboard'],
            ['name' => 'View Revenue Analytics', 'slug' => 'revenue.analytics', 'group_name' => 'SaaS Dashboard'],
            ['name' => 'View Lead Analytics', 'slug' => 'lead.analytics', 'group_name' => 'SaaS Dashboard'],
            ['name' => 'View Support Tickets', 'slug' => 'support.view', 'group_name' => 'Support'],
            ['name' => 'Manage Support Tickets', 'slug' => 'support.manage', 'group_name' => 'Support'],
            ['name' => 'Send Email Campaigns', 'slug' => 'email.campaigns', 'group_name' => 'Communication'],
            ['name' => 'Send Notifications', 'slug' => 'notifications.send', 'group_name' => 'Communication'],
            ['name' => 'System Settings', 'slug' => 'system.settings', 'group_name' => 'System'],
            ['name' => 'Manage Backups', 'slug' => 'system.backups', 'group_name' => 'System'],
            ['name' => 'Manage Integrations', 'slug' => 'system.integrations', 'group_name' => 'System'],
        ];

        $this->seedPermissions($permissions);
    }
}

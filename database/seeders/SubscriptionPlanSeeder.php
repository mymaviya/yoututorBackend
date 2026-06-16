<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => '15 Days Free Demo',
                'slug' => 'free-demo',
                'monthly_display_price' => 0,
                'yearly_price' => 0,
                'yearly_saving' => 0,
                'duration_days' => 15,
                'trial_days' => 15,
                'features' => [
                    'All Features Enabled',
                    'Unlimited Questions',
                    'Unlimited Question Papers',
                    'Question Bank Management',
                    'Blueprint Management',
                    'Bloom Taxonomy Distribution',
                    'Difficulty Level Distribution',
                    'Auto Paper Generator',
                    'Teacher Task Management',
                    'Approval Workflow',
                    'Exam Portion Management',
                    'PDF Export',
                    'Reports & Analytics',
                    'Admin & Teacher Login',
                ],
                'is_trial' => true,
                'is_popular' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Essential',
                'slug' => 'essential',
                'monthly_display_price' => 2499,
                'yearly_price' => 24999,
                'yearly_saving' => 4989,
                'duration_days' => 365,
                'trial_days' => 0,
                'features' => [
                    '1 School License',
                    'Up to 30 Staff Users',
                    'Question Bank Management',
                    'Manual Question Paper Creation',
                    'PDF Export',
                    'Teacher Assignments',
                    'Exam Portion Management',
                    'Basic Reports',
                    'Email Support',
                ],
                'is_trial' => false,
                'is_popular' => false,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'monthly_display_price' => 4999,
                'yearly_price' => 49999,
                'yearly_saving' => 9989,
                'duration_days' => 365,
                'trial_days' => 0,
                'features' => [
                    'Unlimited Staff Users',
                    'Blueprint Management',
                    'Bloom Taxonomy Distribution',
                    'Difficulty Level Distribution',
                    'Auto Question Paper Generator',
                    'Teacher Task Monitoring',
                    'Approval Workflow',
                    'Question Paper History',
                    'Academic Reports & Analytics',
                    'Priority Support',
                    'Data Backup',
                ],
                'is_trial' => false,
                'is_popular' => true,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'monthly_display_price' => 9999,
                'yearly_price' => 99999,
                'yearly_saving' => 19989,
                'duration_days' => 365,
                'trial_days' => 0,
                'features' => [
                    'Multi-Campus Support',
                    'Centralized Question Bank',
                    'Role Based Permissions',
                    'Custom Branding',
                    'Advanced Analytics Dashboard',
                    'Question Bank Health Reports',
                    'Academic Audit Reports',
                    'API Integration',
                    'Dedicated Account Manager',
                    'Training Sessions',
                    'WhatsApp Support',
                    'Priority Feature Requests',
                ],
                'is_trial' => false,
                'is_popular' => false,
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanFeatureItem;
use Illuminate\Database\Seeder;

class SubscriptionPlanFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            'free-demo' => [
                'question_bank',
                'manual_paper_creation',
                'pdf_export',
                'teacher_assignments',
                'exam_portion',
                'basic_reports',
            ],

            'essential' => [
                'question_bank',
                'manual_paper_creation',
                'pdf_export',
                'teacher_assignments',
                'exam_portion',
                'basic_reports',
            ],

            'professional' => [
                'question_bank',
                'manual_paper_creation',
                'pdf_export',
                'teacher_assignments',
                'exam_portion',
                'basic_reports',
                'blueprint_management',
                'bloom_distribution',
                'difficulty_distribution',
                'auto_paper_generator',
                'approval_workflow',
                'analytics',
                'data_backup',
            ],

            'enterprise' => [
                'question_bank',
                'manual_paper_creation',
                'pdf_export',
                'teacher_assignments',
                'exam_portion',
                'basic_reports',
                'blueprint_management',
                'bloom_distribution',
                'difficulty_distribution',
                'auto_paper_generator',
                'approval_workflow',
                'analytics',
                'data_backup',
                'custom_branding',
                'api_access',
                'multi_campus',
                'advanced_reports',
            ],

            'lifetime' => [
                'question_bank',
                'manual_paper_creation',
                'pdf_export',
                'teacher_assignments',
                'exam_portion',
                'basic_reports',
                'blueprint_management',
                'bloom_distribution',
                'difficulty_distribution',
                'auto_paper_generator',
                'approval_workflow',
                'analytics',
                'data_backup',
                'custom_branding',
                'api_access',
                'multi_campus',
                'advanced_reports',
            ],
        ];

        foreach ($features as $planSlug => $planFeatures) {
            $plan = SubscriptionPlan::where('slug', $planSlug)->first();

            if (!$plan) {
                continue;
            }

            foreach ($planFeatures as $featureKey) {
                SubscriptionPlanFeatureItem::updateOrCreate(
                    [
                        'subscription_plan_id' => $plan->id,
                        'feature_key' => $featureKey,
                    ],
                    [
                        'is_enabled' => true,
                    ]
                );
            }
        }
    }
}
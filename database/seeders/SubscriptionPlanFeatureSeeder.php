<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanFeatureItem;
use Illuminate\Database\Seeder;

class SubscriptionPlanFeatureSeeder extends Seeder
{
    /**
     * These feature keys must match sidebar_menus.feature_key
     * and the feature keys used by CheckPlanFeature / CheckRouteFeature.
     */
    private array $allFeatureKeys = [
        'academic_setup',
        'question_bank',
        'approval_workflow',
        'manual_paper_creation',
        'blueprint_management',
        'auto_paper_generator',
        'teacher_management',
        'teacher_tasks',
        'exam_portion',
        'basic_reports',
        'analytics',
        'import_export',
        'advanced_security',
        'crm',
        'saas_management',
        'bell_schedule_management',
        'ai_paper_generator',
        'premium_question_bank',
    ];

    public function run(): void
    {
        $featuresByPlan = [
            /**
             * Demo should allow the school to test the complete ERP.
             */
            'free-demo' => $this->allFeatureKeys,

            /**
             * Essential plan: basic academic setup, question bank,
             * manual paper creation and basic reports.
             */
            'essential' => [
                'academic_setup',
                'question_bank',
                'manual_paper_creation',
                'teacher_management',
                'exam_portion',
                'basic_reports',
                'import_export',
                'bell_schedule_management',
            ],

            /**
             * Professional plan: complete assessment workflow,
             * including blueprint, auto generator and analytics.
             */
            'professional' => [
                'academic_setup',
                'question_bank',
                'approval_workflow',
                'manual_paper_creation',
                'blueprint_management',
                'auto_paper_generator',
                'teacher_management',
                'teacher_tasks',
                'exam_portion',
                'basic_reports',
                'analytics',
                'import_export',
                'premium_question_bank',
                'ai_paper_generator',
                'bell_schedule_management',
            ],

            /**
             * Enterprise plan: all school-facing features.
             * saas_management remains effectively superadmin-only by routes/roles,
             * but keeping it enabled is fine for internal/demo superadmin views.
             */
            'enterprise' => $this->allFeatureKeys,
        ];

        foreach ($featuresByPlan as $planSlug => $enabledFeatureKeys) {
            $plan = SubscriptionPlan::where('slug', $planSlug)->first();

            if (! $plan) {
                continue;
            }

            foreach ($this->allFeatureKeys as $featureKey) {
                SubscriptionPlanFeatureItem::updateOrCreate(
                    [
                        'subscription_plan_id' => $plan->id,
                        'feature_key' => $featureKey,
                    ],
                    [
                        'is_enabled' => in_array($featureKey, $enabledFeatureKeys, true),
                    ]
                );
            }
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalogs = [
            [
                'name' => 'Question Paper ERP',
                'project_type' => 'question_paper_erp',
                'description' => 'Complete question bank, blueprint, bloom taxonomy and paper generation ERP.',
                'items' => [
                    ['Question Bank Management', 'Create, edit, approve and manage questions.', 25000, 7],
                    ['Question Type Templates', 'Manage MCQ, short, long, fill, match and custom question types.', 12000, 3],
                    ['Subject & Lesson Management', 'Manage grades, subjects, streams and lessons.', 15000, 5],
                    ['Blueprint Management', 'Create exam-wise paper blueprints with section rules.', 25000, 7],
                    ['Bloom Taxonomy Engine', 'Set bloom percentage distribution and validate papers.', 20000, 5],
                    ['Difficulty Distribution Engine', 'Manage easy, medium and hard question distribution.', 15000, 4],
                    ['Exam Portion Module', 'Assign exam portions to teachers and track completion.', 18000, 5],
                    ['Teacher Assignment System', 'Assign question creation tasks to teachers.', 18000, 5],
                    ['Approval Workflow', 'Approve, reject and revise teacher-submitted questions.', 15000, 4],
                    ['Auto Paper Generator', 'Generate question papers automatically from blueprint rules.', 30000, 8],
                    ['PDF Export & Print Format', 'Generate printable question paper PDF.', 15000, 4],
                    ['Admin Dashboard & Analytics', 'Reports for question bank health, bloom coverage and progress.', 22000, 6],
                    ['Role & Permission Management', 'Control access for superadmin, admin and teachers.', 15000, 4],
                    ['Subscription & License Management', 'Manage client license, plans and validity.', 25000, 7],
                    ['Deployment & Training', 'Server deployment, testing and basic training.', 20000, 5],
                ],
            ],
            [
                'name' => 'College ERP',
                'project_type' => 'college_erp',
                'description' => 'College ERP with admission CRM, student ERP, website, fees and reports.',
                'items' => [
                    ['Admission CRM', 'Manage enquiries, follow-ups and admissions.', 25000, 7],
                    ['Student ERP', 'Manage student profiles, classes, courses and records.', 40000, 12],
                    ['College Website', 'Dynamic website with admin panel.', 30000, 10],
                    ['Fee Management', 'Manage fee structure, collection and receipts.', 35000, 10],
                    ['Exam Module', 'Manage exams, marks and reports.', 30000, 10],
                    ['Reports Dashboard', 'Admin reports and analytics.', 20000, 6],
                    ['Razorpay Integration', 'Online payment gateway integration.', 15000, 4],
                    ['Deployment & Training', 'Deployment and basic user training.', 20000, 5],
                ],
            ],
            [
                'name' => 'Website Development',
                'project_type' => 'website_development',
                'description' => 'Website development with frontend, backend and deployment.',
                'items' => [
                    ['Website UI Design', 'Responsive website layout and design.', 15000, 5],
                    ['Frontend Development', 'Website pages and frontend implementation.', 20000, 7],
                    ['Admin Panel', 'Content management admin panel.', 25000, 8],
                    ['Contact Form', 'Lead/contact form with email notification.', 7000, 2],
                    ['SEO Basics', 'Basic SEO tags and page structure.', 8000, 2],
                    ['Deployment', 'Hosting deployment and testing.', 10000, 2],
                ],
            ],
        ];

        foreach ($catalogs as $catalogIndex => $catalogData) {
            $catalog = ServiceCatalog::updateOrCreate(
                ['slug' => Str::slug($catalogData['name'])],
                [
                    'name' => $catalogData['name'],
                    'project_type' => $catalogData['project_type'],
                    'description' => $catalogData['description'],
                    'is_active' => true,
                    'sort_order' => $catalogIndex + 1,
                ]
            );

            foreach ($catalogData['items'] as $itemIndex => $item) {
                ServiceCatalogItem::updateOrCreate(
                    [
                        'service_catalog_id' => $catalog->id,
                        'module_name' => $item[0],
                    ],
                    [
                        'description' => $item[1],
                        'quantity' => 1,
                        'unit_price' => $item[2],
                        'total' => $item[2],
                        'timeline_days' => $item[3],
                        'is_optional' => false,
                        'is_active' => true,
                        'sort_order' => $itemIndex + 1,
                    ]
                );
            }
        }
    }
}
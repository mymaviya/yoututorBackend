<?php

namespace Database\Seeders;

use App\Models\ProposalTemplate;
use App\Models\ProposalTemplateSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProposalTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Question Paper ERP',
                'project_type' => 'question_paper_erp',
                'description' => 'Complete Question Bank, Blueprint, Bloom Taxonomy, Exam Portion, Question Paper Generation and Assessment ERP.',
                'sections' => $this->questionPaperErpSections(),
            ],
            [
                'name' => 'School ERP',
                'project_type' => 'school_erp',
                'description' => 'Complete ERP solution for schools including students, fees, exams, attendance, staff, and reports.',
                'sections' => $this->schoolErpSections(),
            ],
            [
                'name' => 'College ERP',
                'project_type' => 'college_erp',
                'description' => 'College ERP with admission, students, departments, courses, fees, exams, website, and reports.',
                'sections' => $this->collegeErpSections(),
            ],
            [
                'name' => 'Hospital ERP',
                'project_type' => 'hospital_erp',
                'description' => 'Hospital management system with OPD, IPD, billing, pharmacy, pathology, and reports.',
                'sections' => $this->hospitalErpSections(),
            ],
            [
                'name' => 'CRM',
                'project_type' => 'crm',
                'description' => 'Customer relationship management system for lead tracking, follow-up, proposal, quotation, and billing.',
                'sections' => $this->crmSections(),
            ],
            [
                'name' => 'Website Development',
                'project_type' => 'website_development',
                'description' => 'Professional website development with admin panel and content management.',
                'sections' => $this->websiteSections(),
            ],
            [
                'name' => 'Mobile App',
                'project_type' => 'mobile_app',
                'description' => 'Android and iOS mobile application development proposal.',
                'sections' => $this->mobileAppSections(),
            ],
            [
                'name' => 'Digital Marketing',
                'project_type' => 'digital_marketing',
                'description' => 'Digital marketing proposal covering SEO, social media, creatives, campaigns, and reporting.',
                'sections' => $this->digitalMarketingSections(),
            ],
            [
                'name' => 'Custom Software',
                'project_type' => 'custom_software',
                'description' => 'Custom software development proposal based on client-specific requirements.',
                'sections' => $this->customSoftwareSections(),
            ],
            [
                'name' => 'AMC & Support',
                'project_type' => 'amc_support',
                'description' => 'Annual maintenance contract and technical support proposal.',
                'sections' => $this->amcSections(),
            ],
        ];

        foreach ($templates as $index => $templateData) {
            $template = ProposalTemplate::updateOrCreate(
                ['slug' => Str::slug($templateData['name'])],
                [
                    'name' => $templateData['name'],
                    'project_type' => $templateData['project_type'],
                    'description' => $templateData['description'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );

            foreach ($templateData['sections'] as $sectionIndex => $section) {
                ProposalTemplateSection::updateOrCreate(
                    [
                        'proposal_template_id' => $template->id,
                        'section_key' => $section['section_key'],
                    ],
                    [
                        'title' => $section['title'],
                        'content' => $section['content'],
                        'sort_order' => $sectionIndex + 1,
                        'is_editable' => true,
                    ]
                );
            }
        }
    }

    private function commonSections(string $projectName, string $scope, string $deliverables): array
    {
        return [
            [
                'title' => 'Cover Page',
                'section_key' => 'cover_page',
                'content' => '<h2>Proposal for ' . $projectName . '</h2><p>Prepared by <strong>Maviya IT Services</strong>.</p>',
            ],
            [
                'title' => 'About Company',
                'section_key' => 'about_company',
                'content' => '<p>Maviya IT Services provides professional software development, ERP solutions, website development, CRM systems, and digital transformation services for institutions and businesses.</p>',
            ],
            [
                'title' => 'Introduction',
                'section_key' => 'introduction',
                'content' => '<p>This proposal outlines the recommended solution, scope of work, deliverables, timeline, and commercial details for the project.</p>',
            ],
            [
                'title' => 'Scope of Work',
                'section_key' => 'scope_of_work',
                'content' => $scope,
            ],
            [
                'title' => 'Deliverables',
                'section_key' => 'deliverables',
                'content' => $deliverables,
            ],
            [
                'title' => 'Timeline',
                'section_key' => 'timeline',
                'content' => '<p>The final project timeline will be decided after requirement confirmation and approval of the proposal.</p>',
            ],
            [
                'title' => 'Commercials',
                'section_key' => 'commercials',
                'content' => '<p>The commercial details will be calculated based on the selected modules, customization, GST applicability, and final scope of work.</p>',
            ],
            [
                'title' => 'Terms & Conditions',
                'section_key' => 'terms_conditions',
                'content' => '<ul><li>Work will start after advance payment.</li><li>Final delivery depends on timely content and approval from the client.</li><li>Additional requirements outside the approved scope may be charged separately.</li><li>GST will be applicable as per government rules.</li></ul>',
            ],
        ];
    }

    private function questionPaperErpSections(): array
    {
        return $this->commonSections(
            'YouTutor Question Paper ERP',
            '
        <p>
        The proposed solution includes a complete Question Bank Management
        and Question Paper Generation ERP designed for Schools,
        Colleges, Coaching Institutes, Educational Groups and Publishers.
        </p>

        <p>
        The system automates question creation, blueprint management,
        bloom taxonomy distribution, difficulty distribution,
        exam portion management, approval workflow,
        auto paper generation and PDF export.
        </p>
        ',
            '
        <ul>
            <li>Question Bank Management</li>
            <li>Question Type Templates</li>
            <li>Subject Templates</li>
            <li>Lesson Management</li>
            <li>Blueprint Management</li>
            <li>Bloom Taxonomy Distribution</li>
            <li>Difficulty Distribution</li>
            <li>Exam Portion Management</li>
            <li>Teacher Assignment System</li>
            <li>Question Approval Workflow</li>
            <li>Question Import & Export</li>
            <li>Auto Question Paper Generator</li>
            <li>Blueprint Validation Engine</li>
            <li>PDF Question Paper Export</li>
            <li>Teacher Dashboard</li>
            <li>Admin Dashboard & Analytics</li>
            <li>Multi School SaaS Support</li>
            <li>Role & Permission Management</li>
            <li>Subscription & License Management</li>
        </ul>
        '
        );
    }

    private function schoolErpSections(): array
    {
        return $this->commonSections(
            'School ERP',
            '<p>The scope includes student management, staff management, attendance, fee management, exam management, question bank, reports, and admin dashboard.</p>',
            '<ul><li>Admin Panel</li><li>Student Module</li><li>Teacher Module</li><li>Fee Module</li><li>Exam Module</li><li>Reports</li></ul>'
        );
    }

    private function collegeErpSections(): array
    {
        return $this->commonSections(
            'College ERP',
            '<p>The scope includes admission CRM, student ERP, course management, department management, fee management, exam records, website management, and reporting.</p>',
            '<ul><li>Admission CRM</li><li>Student ERP</li><li>College Website</li><li>Fees Module</li><li>Exam Module</li><li>Reports</li></ul>'
        );
    }

    private function hospitalErpSections(): array
    {
        return $this->commonSections(
            'Hospital ERP',
            '<p>The scope includes OPD, IPD, appointment, billing, pharmacy, pathology, radiology, patient records, and reports.</p>',
            '<ul><li>Patient Registration</li><li>OPD/IPD</li><li>Billing</li><li>Pharmacy</li><li>Pathology</li><li>Reports</li></ul>'
        );
    }

    private function crmSections(): array
    {
        return $this->commonSections(
            'CRM',
            '<p>The scope includes lead management, follow-up, proposal, quotation, invoice, payment tracking, and customer communication.</p>',
            '<ul><li>Lead Management</li><li>Follow-up System</li><li>Proposal Builder</li><li>Quotation Builder</li><li>Invoice Module</li></ul>'
        );
    }

    private function websiteSections(): array
    {
        return $this->commonSections(
            'Website Development',
            '<p>The scope includes website design, frontend development, backend admin panel, content management, contact forms, SEO basics, and deployment.</p>',
            '<ul><li>Responsive Website</li><li>Admin Panel</li><li>Content Pages</li><li>Contact Form</li><li>Deployment</li></ul>'
        );
    }

    private function mobileAppSections(): array
    {
        return $this->commonSections(
            'Mobile App',
            '<p>The scope includes mobile app UI, API integration, authentication, notifications, user dashboard, and publishing support.</p>',
            '<ul><li>Android App</li><li>iOS App if required</li><li>API Integration</li><li>Push Notifications</li><li>User Dashboard</li></ul>'
        );
    }

    private function digitalMarketingSections(): array
    {
        return $this->commonSections(
            'Digital Marketing',
            '<p>The scope includes social media creatives, campaign planning, SEO basics, content support, performance tracking, and reporting.</p>',
            '<ul><li>Social Media Management</li><li>Creatives</li><li>SEO Basics</li><li>Campaign Support</li><li>Monthly Report</li></ul>'
        );
    }

    private function customSoftwareSections(): array
    {
        return $this->commonSections(
            'Custom Software',
            '<p>The scope will be prepared according to the client’s custom business process, modules, users, reports, and automation requirements.</p>',
            '<ul><li>Requirement Analysis</li><li>Custom Modules</li><li>Admin Panel</li><li>Reports</li><li>Deployment</li></ul>'
        );
    }

    private function amcSections(): array
    {
        return $this->commonSections(
            'AMC & Support',
            '<p>The scope includes software maintenance, bug fixing, minor updates, backup support, monitoring, and technical assistance.</p>',
            '<ul><li>Technical Support</li><li>Bug Fixing</li><li>Minor Updates</li><li>Backup Support</li><li>Monthly Maintenance</li></ul>'
        );
    }
}

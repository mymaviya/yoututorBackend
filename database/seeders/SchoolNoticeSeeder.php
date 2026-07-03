<?php

namespace Database\Seeders;

use App\Models\SchoolNotice;
use Illuminate\Database\Seeder;

class SchoolNoticeSeeder extends Seeder
{
    public function run(): void
    {
        $notices = [
            [
                'title' => 'Welcome to New Academic Session',
                'description' => 'We wish all students and teachers a successful academic year.',
                'icon' => '🎓',
                'priority' => 10,
            ],
            [
                'title' => 'Staff Meeting',
                'description' => 'Staff meeting today at 3:00 PM in the conference hall.',
                'icon' => '👨‍🏫',
                'priority' => 9,
            ],
            [
                'title' => 'Unit Test Schedule Published',
                'description' => 'Unit Test timetable for Classes 6 to 10 has been published.',
                'icon' => '📝',
                'priority' => 8,
            ],
            [
                'title' => 'Science Exhibition',
                'description' => 'Inter-school Science Exhibition will be held on 15 July.',
                'icon' => '🔬',
                'priority' => 7,
            ],
            [
                'title' => 'Parent Teacher Meeting',
                'description' => 'Monthly PTM will be conducted on the last Saturday of this month.',
                'icon' => '👪',
                'priority' => 6,
            ],
            [
                'title' => 'School Holiday',
                'description' => 'School will remain closed on account of Muharram.',
                'icon' => '🏖️',
                'priority' => 10,
            ],
        ];

        foreach ($notices as $notice) {
            SchoolNotice::updateOrCreate(
                [
                    'title' => $notice['title'],
                ],
                [
                    'description' => $notice['description'],
                    'icon' => $notice['icon'],
                    'priority' => $notice['priority'],

                    'start_date' => now()->subDays(1),
                    'end_date' => now()->addMonths(6),

                    'show_on_dashboard' => true,
                    'show_on_website' => false,
                    'show_to_students' => true,
                    'show_to_teachers' => true,
                    'show_to_parents' => true,

                    'is_active' => true,
                ]
            );
        }
    }
}
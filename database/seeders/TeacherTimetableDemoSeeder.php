<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolBell;
use App\Models\Section;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\TeacherTimetable;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeacherTimetableDemoSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::where('role', 'teacher')->first();

        if (!$teacher) {
            return;
        }

        $academicYear = AcademicYear::first();
        $grade = Grade::first();
        $section = Section::first();
        $stream = Stream::first();

        $subjects = Subject::take(6)->get();
        $bells = SchoolBell::orderBy('sort_order')->take(8)->get();

        $days = [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
        ];

        foreach ($days as $day) {

            foreach ($bells as $index => $bell) {

                $subject = $subjects[$index % $subjects->count()];

                TeacherTimetable::updateOrCreate(
                    [
                        'teacher_id' => $teacher->id,
                        'weekday' => $day,
                        'school_bell_id' => $bell->id,
                    ],
                    [
                        'timetable_entry_id' => ($index + 1),
                        'grade_id' => $grade?->id,
                        'section_id' => $section?->id,
                        'stream_id' => $stream?->id,
                        'subject_id' => $subject->id,
                        'room_no' => 'R-' . rand(101, 305),
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
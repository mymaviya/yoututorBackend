<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\SchoolBell;
use App\Models\Section;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\TeacherTimetable;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherTimetableFactory extends Factory
{
    protected $model = TeacherTimetable::class;

    public function definition(): array
    {
        return [
            'timetable_entry_id' => TimetableEntry::factory(),

            'teacher_id' => User::factory(),

            'grade_id' => Grade::factory(),

            'section_id' => Section::factory(),

            'stream_id' => Stream::factory(),

            'subject_id' => Subject::factory(),

            'school_bell_id' => SchoolBell::factory(),

            'weekday' => $this->faker->randomElement([
                'Monday',
                'Tuesday',
                'Wednesday',
                'Thursday',
                'Friday',
                'Saturday',
            ]),

            'room_no' => strtoupper(
                $this->faker->bothify('R-##?')
            ),

            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
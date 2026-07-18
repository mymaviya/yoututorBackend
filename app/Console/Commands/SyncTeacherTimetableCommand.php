<?php

namespace App\Console\Commands;

use App\Models\TeacherTimetable;
use App\Models\TimetableEntry;
use Illuminate\Console\Command;

class SyncTeacherTimetableCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * php artisan teacher-timetable:sync
     */
    protected $signature = 'teacher-timetable:sync';

    /**
     * The console command description.
     */
    protected $description = 'Synchronize teacher_timetable_views from timetable_entries';

    public function handle(): int
    {
        $this->info('Synchronizing Teacher Timetable...');

        $count = 0;

        TimetableEntry::with('weeklyTimetable')
            ->chunk(200, function ($entries) use (&$count) {

                foreach ($entries as $entry) {

                    if (! $entry->weeklyTimetable) {
                        continue;
                    }

                    TeacherTimetable::updateOrCreate(
                        [
                            'timetable_entry_id' => $entry->id,
                        ],
                        [
                            'teacher_id'     => $entry->teacher_id,
                            'grade_id'       => $entry->weeklyTimetable->grade_id,
                            'section_id'     => $entry->weeklyTimetable->section_id,
                            'stream_id'      => $entry->weeklyTimetable->stream_id,
                            'subject_id'     => $entry->subject_id,
                            'school_bell_id' => $entry->school_bell_id,
                            'weekday'        => $entry->weekday,
                            'room_no'        => $entry->room_no,
                            'is_active'      => (bool) $entry->is_active,
                        ]
                    );

                    $count++;
                }
            });

        $this->newLine();
        $this->info("✔ {$count} timetable records synchronized successfully.");

        return self::SUCCESS;
    }
}
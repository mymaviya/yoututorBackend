<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\TimetableEntry;
use App\Notifications\TeacherTimetableUpdatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyTeacherTimetableUpdated implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        protected TimetableEntry $entry
    ) {
    }

    public function handle(): void
    {
        $this->entry->loadMissing([
            'teacher',
            'substituteTeacher',
            'weeklyTimetable',
            'bell',
            'subject',
        ]);

        $users = User::query()
            ->where(function ($query) {
                $query->where('subscription_id', $this->entry->subscription_id)
                    ->orWhere('id', $this->entry->teacher_id)
                    ->orWhere('id', $this->entry->substitute_teacher_id);
            })
            ->get();

        foreach ($users as $user) {
            $user->notify(
                new TeacherTimetableUpdatedNotification($this->entry)
            );
        }
    }
}
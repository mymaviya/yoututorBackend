<?php

namespace App\Events;

use App\Models\TeacherSubstitution;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeacherSubstitutionAutoAssigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public TeacherSubstitution $substitution
    ) {
        $this->substitution->loadMissing([
            'originalTeacher',
            'absentTeacher',
            'substituteTeacher',
            'timetableEntry.bell',
            'grade',
            'section',
            'subject',
        ]);
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherTimetableResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'timetable_entry_id' => $this->timetable_entry_id,

            'weekday' => $this->weekday,

            'teacher' => [
                'id' => $this->teacher?->id,
                'name' => $this->teacher?->name,
                'employee_code' => $this->teacher?->employee_code,
            ],

            'grade' => [
                'id' => $this->grade?->id,
                'name' => $this->grade?->name,
            ],

            'section' => [
                'id' => $this->section?->id,
                'name' => $this->section?->name,
            ],

            'stream' => [
                'id' => $this->stream?->id,
                'name' => $this->stream?->name,
            ],

            'subject' => [
                'id' => $this->subject?->id,
                'name' => $this->subject?->name,
                'code' => $this->subject?->code,
            ],

            'bell' => [
                'id' => $this->bell?->id,
                'period_number' => $this->bell?->period_number,
                'title' => $this->bell?->title,
                'start_time' => $this->bell?->start_time,
                'end_time' => $this->bell?->end_time,
            ],

            'room_no' => $this->room_no,

            'is_active' => (bool) $this->is_active,

            'is_substitution' => (bool) optional($this->timetableEntry)->is_substitution,

            'substitute_teacher' => optional($this->timetableEntry)->substituteTeacher
                ? [
                    'id' => $this->timetableEntry->substituteTeacher->id,
                    'name' => $this->timetableEntry->substituteTeacher->name,
                ]
                : null,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
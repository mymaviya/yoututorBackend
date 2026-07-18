<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeacherTimetableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check()
            && auth()->user()->can('teacher.timetable');
    }

    public function rules(): array
    {
        return [
            'mode' => [
                'nullable',
                'in:teacher,class',
            ],

            'academic_year_id' => [
                'nullable',
                'exists:academic_years,id',
            ],

            'teacher_id' => [
                'nullable',
                'exists:users,id',
            ],

            'grade_id' => [
                'nullable',
                'exists:grades,id',
            ],

            'section_id' => [
                'nullable',
                'exists:sections,id',
            ],

            'stream_id' => [
                'nullable',
                'exists:streams,id',
            ],

            'weekday' => [
                'nullable',
                'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            ],

            'school_bell_id' => [
                'nullable',
                'exists:school_bells,id',
            ],

            'export' => [
                'nullable',
                'boolean',
            ],

            'print' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'teacher_id.exists' => 'Selected teacher does not exist.',
            'grade_id.exists' => 'Selected grade does not exist.',
            'section_id.exists' => 'Selected section does not exist.',
            'stream_id.exists' => 'Selected stream does not exist.',
            'academic_year_id.exists' => 'Selected academic year does not exist.',
            'weekday.in' => 'Invalid weekday selected.',
        ];
    }
}
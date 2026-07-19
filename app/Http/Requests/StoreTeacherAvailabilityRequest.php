<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'academic_year_id' => [
                'required',
                'integer',
                Rule::exists('academic_years', 'id'),
            ],

            'teacher_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],

            'availability' => [
                'required',
                'array',
                'min:1',
            ],

            'availability.*.weekday' => [
                'required',
                'integer',
                'between:1,7',
            ],

            'availability.*.period_no' => [
                'required',
                'integer',
                'min:1',
                'max:20',
            ],

            'availability.*.status' => [
                'required',
                Rule::in([
                    'available',
                    'preferred',
                    'unavailable',
                ]),
            ],

            'availability.*.reason_type' => [
                'nullable',
                'string',
                'max:50',
            ],

            'availability.*.reason' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [

            'teacher_id.required' =>
                'Please select a teacher.',

            'academic_year_id.required' =>
                'Please select an academic year.',

            'availability.required' =>
                'Availability data is required.',

            'availability.*.weekday.required' =>
                'Weekday is required.',

            'availability.*.period_no.required' =>
                'Period number is required.',

            'availability.*.status.required' =>
                'Availability status is required.',
        ];
    }
}
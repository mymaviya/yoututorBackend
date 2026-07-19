<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $subscriptionId = auth()->user()?->subscription_id;

        return [
            'academic_year_id' => [
                'required',
                'integer',
                Rule::exists('academic_years', 'id')
                    ->where(fn ($query) => $query
                        ->where('subscription_id', $subscriptionId)
                        ->where('is_active', true)),
            ],

            'teacher_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query
                        ->where('subscription_id', $subscriptionId)
                        ->where('is_active', true)),
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
            'teacher_id.required' => 'Please select a teacher.',
            'teacher_id.exists' => 'The selected teacher is invalid or does not belong to your subscription.',
            'academic_year_id.required' => 'Please select an academic year.',
            'academic_year_id.exists' => 'The selected academic year is invalid or does not belong to your subscription.',
            'availability.required' => 'Availability data is required.',
            'availability.*.weekday.required' => 'Weekday is required.',
            'availability.*.period_no.required' => 'Period number is required.',
            'availability.*.status.required' => 'Availability status is required.',
        ];
    }
}

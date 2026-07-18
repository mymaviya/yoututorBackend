<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:20'],

            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],

            'show_on_dashboard' => ['boolean'],
            'show_on_website' => ['boolean'],
            'show_to_students' => ['boolean'],
            'show_to_teachers' => ['boolean'],
            'show_to_parents' => ['boolean'],

            'priority' => ['required', 'integer', 'min:1', 'max:10'],
            'is_active' => ['boolean'],
        ];
    }
}
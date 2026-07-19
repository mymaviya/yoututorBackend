<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateTimetableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules.
     */
    public function rules(): array
    {
        return [

            'subscription_id' => [
                'required',
                'integer',
                'exists:subscriptions,id',
            ],

            'academic_year_id' => [
                'required',
                'integer',
                'exists:academic_years,id',
            ],

            'grade_id' => [
                'required',
                'integer',
                'exists:grades,id',
            ],

            'section_id' => [
                'required',
                'integer',
                'exists:sections,id',
            ],

            'stream_id' => [
                'nullable',
                'integer',
                'exists:streams,id',
            ],

            'working_days' => [
                'required',
                'integer',
                'min:1',
                'max:7',
            ],

            'periods_per_day' => [
                'required',
                'integer',
                'min:1',
                'max:20',
            ],

            'algorithm' => [
                'nullable',
                'in:greedy,backtracking,genetic',
            ],

            'max_iterations' => [
                'nullable',
                'integer',
                'min:1000',
            ],

            'population_size' => [
                'nullable',
                'integer',
                'min:20',
                'max:1000',
            ],

            'generations' => [
                'nullable',
                'integer',
                'min:10',
                'max:5000',
            ],

            'mutation_rate' => [
                'nullable',
                'numeric',
                'between:0,1',
            ],

            'crossover_rate' => [
                'nullable',
                'numeric',
                'between:0,1',
            ],

            'elite_count' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
            ],

            'max_teacher_periods_per_day' => [
                'nullable',
                'integer',
                'min:1',
                'max:20',
            ],

            'max_consecutive_periods' => [
                'nullable',
                'integer',
                'min:1',
                'max:10',
            ],

            'teacher_schedule' => [
                'nullable',
                'array',
            ],

            'teacher_schedule.*' => [
                'array',
            ],

            'lunch_break_periods' => [
                'nullable',
                'array',
            ],

            'lunch_break_periods.*' => [
                'integer',
                'min:1',
                'max:20',
            ],
        ];
    }

    /**
     * Default values.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([

            'algorithm' => $this->algorithm ?? 'backtracking',

            'working_days' => $this->working_days ?? 6,

            'periods_per_day' => $this->periods_per_day ?? 8,

            'population_size' => $this->population_size ?? 100,

            'generations' => $this->generations ?? 300,

            'mutation_rate' => $this->mutation_rate ?? 0.08,

            'crossover_rate' => $this->crossover_rate ?? 0.80,

            'elite_count' => $this->elite_count ?? 5,

            'max_iterations' => $this->max_iterations ?? 500000,

        ]);
    }

    /**
     * Custom messages.
     */
    public function messages(): array
    {
        return [

            'subscription_id.required' => 'Subscription is required.',

            'academic_year_id.required' => 'Academic Year is required.',

            'grade_id.required' => 'Grade is required.',

            'section_id.required' => 'Section is required.',

            'working_days.required' => 'Working days are required.',

            'periods_per_day.required' => 'Periods per day are required.',
        ];
    }
}
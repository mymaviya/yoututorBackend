<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubjectPeriodAllocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\SubjectPeriodAllocationExport;
use App\Exports\SubjectPeriodAllocationTemplateExport;
use App\Imports\SubjectPeriodAllocationImport;
use Maatwebsite\Excel\Facades\Excel;

class SubjectPeriodAllocationController extends Controller
{
    public function index(Request $request)
    {
        $query = SubjectPeriodAllocation::with([
            'grade',
            'section',
            'stream',
            'subject',
            'preferredTeacher',
        ]);

        if ($request->filled('grade_id')) {
            $query->where('grade_id', $request->grade_id);
        }

        if ($request->filled('section_id')) {
            $query->where('section_id', $request->section_id);
        }

        if ($request->filled('stream_id')) {
            $query->where('stream_id', $request->stream_id);
        }

        if ($request->filled('subject_category')) {
            $query->where('subject_category', $request->subject_category);
        }

        return response()->json([
            'success' => true,
            'data' => $query
                ->latest()
                ->paginate($request->get('per_page', 20)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'grade_id' => 'required|exists:grades,id',
            'section_id' => 'nullable|exists:sections,id',
            'stream_id' => 'nullable|exists:streams,id',
            'subject_id' => 'required|exists:subjects,id',
            'preferred_teacher_id' => 'nullable|exists:users,id',

            'subject_category' => 'required|in:major,minor,language,elective,lab,activity',
            'weekly_periods' => 'required|integer|min:1|max:60',
            'max_periods_per_day' => 'required|integer|min:1|max:10',

            'prefer_double_period' => 'boolean',
            'prefer_morning' => 'boolean',
            'prefer_last_period' => 'boolean',
            'prefer_saturday' => 'boolean',

            'is_optional' => 'boolean',
            'is_parallel_subject' => 'boolean',
            'parallel_group_code' => 'nullable|string|max:100',

            'priority' => 'nullable|integer|min:1|max:10',
            'is_active' => 'boolean',
        ]);

        $data['subscription_id'] = auth()->user()->subscription_id;

        $allocation = SubjectPeriodAllocation::updateOrCreate(
            [
                'subscription_id' => $data['subscription_id'],
                'academic_year_id' => $data['academic_year_id'] ?? null,
                'grade_id' => $data['grade_id'],
                'section_id' => $data['section_id'] ?? null,
                'stream_id' => $data['stream_id'] ?? null,
                'subject_id' => $data['subject_id'],
            ],
            $data
        );

        return response()->json([
            'success' => true,
            'message' => 'Subject period allocation saved successfully.',
            'data' => $allocation->load([
                'grade',
                'section',
                'stream',
                'subject',
                'preferredTeacher',
            ]),
        ]);
    }

    public function show(SubjectPeriodAllocation $subjectPeriodAllocation)
    {
        return response()->json([
            'success' => true,
            'data' => $subjectPeriodAllocation->load([
                'grade',
                'section',
                'stream',
                'subject',
                'preferredTeacher',
            ]),
        ]);
    }

    public function update(Request $request, SubjectPeriodAllocation $subjectPeriodAllocation)
    {
        $data = $request->validate([
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'grade_id' => 'required|exists:grades,id',
            'section_id' => 'nullable|exists:sections,id',
            'stream_id' => 'nullable|exists:streams,id',
            'subject_id' => 'required|exists:subjects,id',
            'preferred_teacher_id' => 'nullable|exists:users,id',

            'subject_category' => 'required|in:major,minor,language,elective,lab,activity',
            'weekly_periods' => 'required|integer|min:1|max:60',
            'max_periods_per_day' => 'required|integer|min:1|max:10',

            'prefer_double_period' => 'boolean',
            'prefer_morning' => 'boolean',
            'prefer_last_period' => 'boolean',
            'prefer_saturday' => 'boolean',

            'is_optional' => 'boolean',
            'is_parallel_subject' => 'boolean',
            'parallel_group_code' => 'nullable|string|max:100',

            'priority' => 'nullable|integer|min:1|max:10',
            'is_active' => 'boolean',
        ]);

        $subjectPeriodAllocation->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Subject period allocation updated successfully.',
            'data' => $subjectPeriodAllocation->fresh([
                'grade',
                'section',
                'stream',
                'subject',
                'preferredTeacher',
            ]),
        ]);
    }

    public function destroy(SubjectPeriodAllocation $subjectPeriodAllocation)
    {
        $subjectPeriodAllocation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subject period allocation deleted successfully.',
        ]);
    }

    public function bulkSave(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'grade_id' => 'required|exists:grades,id',
            'section_id' => 'nullable|exists:sections,id',
            'stream_id' => 'nullable|exists:streams,id',
            'items' => 'required|array|min:1',

            'items.*.subject_id' => 'required|exists:subjects,id',
            'items.*.preferred_teacher_id' => 'nullable|exists:users,id',
            'items.*.subject_category' => 'required|in:major,minor,language,elective,lab,activity',
            'items.*.weekly_periods' => 'required|integer|min:1|max:60',
            'items.*.max_periods_per_day' => 'required|integer|min:1|max:10',

            'items.*.prefer_double_period' => 'boolean',
            'items.*.prefer_morning' => 'boolean',
            'items.*.prefer_last_period' => 'boolean',
            'items.*.prefer_saturday' => 'boolean',
            'items.*.is_optional' => 'boolean',
            'items.*.is_parallel_subject' => 'boolean',
            'items.*.parallel_group_code' => 'nullable|string|max:100',
            'items.*.priority' => 'nullable|integer|min:1|max:10',
            'items.*.is_active' => 'boolean',
        ]);

        $subscriptionId = auth()->user()->subscription_id;

        DB::transaction(function () use ($data, $subscriptionId) {
            foreach ($data['items'] as $item) {
                SubjectPeriodAllocation::updateOrCreate(
                    [
                        'subscription_id' => $subscriptionId,
                        'academic_year_id' => $data['academic_year_id'] ?? null,
                        'grade_id' => $data['grade_id'],
                        'section_id' => $data['section_id'] ?? null,
                        'stream_id' => $data['stream_id'] ?? null,
                        'subject_id' => $item['subject_id'],
                    ],
                    array_merge($item, [
                        'subscription_id' => $subscriptionId,
                        'academic_year_id' => $data['academic_year_id'] ?? null,
                        'grade_id' => $data['grade_id'],
                        'section_id' => $data['section_id'] ?? null,
                        'stream_id' => $data['stream_id'] ?? null,
                    ])
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Subject period allocations saved successfully.',
        ]);
    }

    public function bulkEditorData(Request $request)
    {
        $data = $request->validate([
            'grade_id' => 'required|exists:grades,id',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'section_id' => 'nullable|exists:sections,id',
            'stream_id' => 'nullable|exists:streams,id',
        ]);

        $subscriptionId = auth()->user()->subscription_id;

        $subjects = \App\Models\Subject::query()
            ->where('subscription_id', $subscriptionId)
            ->where('grade_id', $data['grade_id'])
            ->when($data['stream_id'] ?? null, fn($q) => $q->where('stream_id', $data['stream_id']))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $allocations = SubjectPeriodAllocation::query()
            ->where('subscription_id', $subscriptionId)
            ->where('grade_id', $data['grade_id'])
            ->where('academic_year_id', $data['academic_year_id'] ?? null)
            ->where('section_id', $data['section_id'] ?? null)
            ->where('stream_id', $data['stream_id'] ?? null)
            ->get()
            ->keyBy('subject_id');

        $rows = $subjects->map(function ($subject) use ($allocations) {
            $existing = $allocations->get($subject->id);

            return [
                'id' => $existing?->id,
                'subject_id' => $subject->id,
                'subject_name' => $subject->name,
                'preferred_teacher_id' => $existing?->preferred_teacher_id,
                'subject_category' => $existing?->subject_category ?? 'major',
                'weekly_periods' => $existing?->weekly_periods ?? 6,
                'max_periods_per_day' => $existing?->max_periods_per_day ?? 2,
                'prefer_double_period' => (bool) ($existing?->prefer_double_period ?? false),
                'prefer_morning' => (bool) ($existing?->prefer_morning ?? false),
                'prefer_last_period' => (bool) ($existing?->prefer_last_period ?? false),
                'prefer_saturday' => (bool) ($existing?->prefer_saturday ?? false),
                'is_optional' => (bool) ($existing?->is_optional ?? false),
                'is_parallel_subject' => (bool) ($existing?->is_parallel_subject ?? false),
                'parallel_group_code' => $existing?->parallel_group_code,
                'priority' => $existing?->priority ?? 5,
                'is_active' => (bool) ($existing?->is_active ?? true),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function copyGrade(Request $request)
    {
        $data = $request->validate([
            'from_academic_year_id' => 'nullable|exists:academic_years,id',
            'to_academic_year_id' => 'nullable|exists:academic_years,id',

            'from_grade_id' => 'required|exists:grades,id',
            'to_grade_id' => 'required|exists:grades,id',

            'from_section_id' => 'nullable|exists:sections,id',
            'to_section_id' => 'nullable|exists:sections,id',

            'from_stream_id' => 'nullable|exists:streams,id',
            'to_stream_id' => 'nullable|exists:streams,id',
        ]);

        $subscriptionId = auth()->user()->subscription_id;

        $sourceAllocations = SubjectPeriodAllocation::query()
            ->where('subscription_id', $subscriptionId)
            ->where('academic_year_id', $data['from_academic_year_id'] ?? null)
            ->where('grade_id', $data['from_grade_id'])
            ->where('section_id', $data['from_section_id'] ?? null)
            ->where('stream_id', $data['from_stream_id'] ?? null)
            ->get();

        foreach ($sourceAllocations as $allocation) {
            SubjectPeriodAllocation::updateOrCreate(
                [
                    'subscription_id' => $subscriptionId,
                    'academic_year_id' => $data['to_academic_year_id'] ?? null,
                    'grade_id' => $data['to_grade_id'],
                    'section_id' => $data['to_section_id'] ?? null,
                    'stream_id' => $data['to_stream_id'] ?? null,
                    'subject_id' => $allocation->subject_id,
                ],
                [
                    'preferred_teacher_id' => $allocation->preferred_teacher_id,
                    'subject_category' => $allocation->subject_category,
                    'weekly_periods' => $allocation->weekly_periods,
                    'max_periods_per_day' => $allocation->max_periods_per_day,
                    'prefer_double_period' => $allocation->prefer_double_period,
                    'prefer_morning' => $allocation->prefer_morning,
                    'prefer_last_period' => $allocation->prefer_last_period,
                    'prefer_saturday' => $allocation->prefer_saturday,
                    'is_optional' => $allocation->is_optional,
                    'is_parallel_subject' => $allocation->is_parallel_subject,
                    'parallel_group_code' => $allocation->parallel_group_code,
                    'priority' => $allocation->priority,
                    'is_active' => $allocation->is_active,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Grade allocation copied successfully.',
            'copied_count' => $sourceAllocations->count(),
        ]);
    }

    public function template()
{
    return Excel::download(
        new SubjectPeriodAllocationTemplateExport(),
        'subject-period-allocation-template.xlsx'
    );
}

    public function export(Request $request)
    {
        $data = $request->validate([
            'grade_id' => 'required|exists:grades,id',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'section_id' => 'nullable|exists:sections,id',
            'stream_id' => 'nullable|exists:streams,id',
        ]);

        return Excel::download(
            new SubjectPeriodAllocationExport(
                auth()->user()->subscription_id,
                (int) $data['grade_id'],
                $data['academic_year_id'] ?? null,
                $data['section_id'] ?? null,
                $data['stream_id'] ?? null
            ),
            'subject-period-allocations.xlsx'
        );
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'grade_id' => 'required|exists:grades,id',
            'section_id' => 'nullable|exists:sections,id',
            'stream_id' => 'nullable|exists:streams,id',
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        Excel::import(
            new SubjectPeriodAllocationImport(
                auth()->user()->subscription_id,
                (int) $data['grade_id'],
                $data['academic_year_id'] ?? null,
                $data['section_id'] ?? null,
                $data['stream_id'] ?? null
            ),
            $request->file('file')
        );

        return response()->json([
            'success' => true,
            'message' => 'Subject period allocations imported successfully.',
        ]);
    }
}

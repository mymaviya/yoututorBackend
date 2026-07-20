<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\SubjectPeriodAllocationExport;
use App\Exports\SubjectPeriodAllocationTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\SubjectPeriodAllocationImport;
use App\Models\Subject;
use App\Models\SubjectPeriodAllocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as LaravelValidator;
use Maatwebsite\Excel\Facades\Excel;

class SubjectPeriodAllocationController extends Controller
{
    private const CATEGORIES = ['major', 'minor', 'language', 'elective', 'lab', 'activity'];

    public function index(Request $request)
    {
        $subscriptionId = $this->subscriptionId();

        $query = SubjectPeriodAllocation::query()
            ->where('subscription_id', $subscriptionId)
            ->with(['grade', 'section', 'stream', 'subject', 'preferredTeacher']);

        foreach (['academic_year_id', 'grade_id', 'section_id', 'stream_id', 'subject_category'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate($request->integer('per_page', 20)),
        ]);
    }

    public function store(Request $request)
    {
        $subscriptionId = $this->subscriptionId();
        $data = $this->validateAllocation($request, $subscriptionId);
        $data['subscription_id'] = $subscriptionId;

        $allocation = SubjectPeriodAllocation::updateOrCreate(
            $this->identityAttributes($data, $subscriptionId),
            $data
        );

        return response()->json([
            'success' => true,
            'message' => 'Subject period allocation saved successfully.',
            'data' => $allocation->load(['grade', 'section', 'stream', 'subject', 'preferredTeacher']),
        ]);
    }

    public function show(SubjectPeriodAllocation $subjectPeriodAllocation)
    {
        $this->ensureOwned($subjectPeriodAllocation);

        return response()->json([
            'success' => true,
            'data' => $subjectPeriodAllocation->load(['grade', 'section', 'stream', 'subject', 'preferredTeacher']),
        ]);
    }

    public function update(Request $request, SubjectPeriodAllocation $subjectPeriodAllocation)
    {
        $this->ensureOwned($subjectPeriodAllocation);

        $subscriptionId = $this->subscriptionId();
        $data = $this->validateAllocation($request, $subscriptionId);

        $duplicate = SubjectPeriodAllocation::query()
            ->where($this->identityAttributes($data, $subscriptionId))
            ->whereKeyNot($subjectPeriodAllocation->getKey())
            ->exists();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'An allocation already exists for this subject and selected class combination.',
            ], 422);
        }

        $subjectPeriodAllocation->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Subject period allocation updated successfully.',
            'data' => $subjectPeriodAllocation->fresh(['grade', 'section', 'stream', 'subject', 'preferredTeacher']),
        ]);
    }

    public function destroy(SubjectPeriodAllocation $subjectPeriodAllocation)
    {
        $this->ensureOwned($subjectPeriodAllocation);
        $subjectPeriodAllocation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subject period allocation deleted successfully.',
        ]);
    }

    public function bulkSave(Request $request)
    {
        $subscriptionId = $this->subscriptionId();
        $data = $this->validateBulk($request, $subscriptionId);

        DB::transaction(function () use ($data, $subscriptionId) {
            $scope = $this->scopeQuery(
                SubjectPeriodAllocation::query(),
                $subscriptionId,
                $data['academic_year_id'] ?? null,
                (int) $data['grade_id'],
                $data['section_id'] ?? null,
                $data['stream_id'] ?? null
            );

            $submittedSubjectIds = collect($data['items'])
                ->pluck('subject_id')
                ->map(fn ($id) => (int) $id);

            $scope->whereNotIn('subject_id', $submittedSubjectIds)->delete();

            foreach ($data['items'] as $item) {
                $item = $this->normaliseItem($item);

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
            'saved_count' => count($data['items']),
        ]);
    }

    public function bulkEditorData(Request $request)
    {
        $subscriptionId = $this->subscriptionId();
        $data = $this->validateScope($request, $subscriptionId);

        $subjects = Subject::query()
            ->where('subscription_id', $subscriptionId)
            ->where('grade_id', $data['grade_id'])
            ->when(
                $data['stream_id'] ?? null,
                fn (Builder $query, $streamId) => $query->where('stream_id', $streamId)
            )
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $allocations = $this->scopeQuery(
            SubjectPeriodAllocation::query(),
            $subscriptionId,
            $data['academic_year_id'] ?? null,
            (int) $data['grade_id'],
            $data['section_id'] ?? null,
            $data['stream_id'] ?? null
        )->get()->keyBy('subject_id');

        $rows = $subjects->map(function (Subject $subject) use ($allocations) {
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

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function copyGrade(Request $request)
    {
        $subscriptionId = $this->subscriptionId();

        $validator = Validator::make($request->all(), [
            'from_academic_year_id' => ['nullable', $this->ownedExists('academic_years', $subscriptionId)],
            'to_academic_year_id' => ['nullable', $this->ownedExists('academic_years', $subscriptionId)],
            'from_grade_id' => ['required', 'integer', $this->sharedExists('grades')],
            'to_grade_id' => ['required', 'integer', $this->sharedExists('grades')],
            'from_section_id' => ['nullable', 'integer', $this->sharedExists('sections')],
            'to_section_id' => ['nullable', 'integer', $this->sharedExists('sections')],
            'from_stream_id' => ['nullable', 'integer', $this->sharedExists('streams')],
            'to_stream_id' => ['nullable', 'integer', $this->sharedExists('streams')],
        ]);

        $this->addClassRelationshipValidation($validator, 'from_');
        $this->addClassRelationshipValidation($validator, 'to_');
        $data = $validator->validate();

        $sameScope = ($data['from_academic_year_id'] ?? null) == ($data['to_academic_year_id'] ?? null)
            && (int) $data['from_grade_id'] === (int) $data['to_grade_id']
            && ($data['from_section_id'] ?? null) == ($data['to_section_id'] ?? null)
            && ($data['from_stream_id'] ?? null) == ($data['to_stream_id'] ?? null);

        if ($sameScope) {
            return response()->json([
                'success' => false,
                'message' => 'Source and destination allocation cannot be the same.',
            ], 422);
        }

        $sourceAllocations = $this->scopeQuery(
            SubjectPeriodAllocation::query(),
            $subscriptionId,
            $data['from_academic_year_id'] ?? null,
            (int) $data['from_grade_id'],
            $data['from_section_id'] ?? null,
            $data['from_stream_id'] ?? null
        )->get();

        if ($sourceAllocations->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No source allocations were found to copy.',
            ], 422);
        }

        DB::transaction(function () use ($sourceAllocations, $data, $subscriptionId) {
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
                    Arr::only($allocation->toArray(), [
                        'preferred_teacher_id',
                        'subject_category',
                        'weekly_periods',
                        'max_periods_per_day',
                        'prefer_double_period',
                        'prefer_morning',
                        'prefer_last_period',
                        'prefer_saturday',
                        'is_optional',
                        'is_parallel_subject',
                        'parallel_group_code',
                        'priority',
                        'is_active',
                    ])
                );
            }
        });

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
        $subscriptionId = $this->subscriptionId();
        $data = $this->validateScope($request, $subscriptionId);

        return Excel::download(
            new SubjectPeriodAllocationExport(
                $subscriptionId,
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
        $subscriptionId = $this->subscriptionId();
        $validator = Validator::make($request->all(), array_merge($this->scopeRules($subscriptionId), [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]));
        $this->addClassRelationshipValidation($validator);
        $data = $validator->validate();

        Excel::import(
            new SubjectPeriodAllocationImport(
                $subscriptionId,
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

    private function validateAllocation(Request $request, int $subscriptionId): array
    {
        $validator = Validator::make(
            $request->all(),
            array_merge($this->scopeRules($subscriptionId), $this->itemRules($subscriptionId))
        );

        $this->addClassRelationshipValidation($validator);
        $this->addLogicalValidation($validator, fn () => [$request->all()]);

        return $validator->validate();
    }

    private function validateBulk(Request $request, int $subscriptionId): array
    {
        $rules = $this->scopeRules($subscriptionId);
        $rules['items'] = ['required', 'array', 'min:1'];

        foreach ($this->itemRules($subscriptionId) as $key => $rule) {
            $rules['items.*.' . $key] = $rule;
        }

        $rules['items.*.subject_id'][] = 'distinct';

        $validator = Validator::make($request->all(), $rules);
        $this->addClassRelationshipValidation($validator);
        $this->addLogicalValidation($validator, fn () => $request->input('items', []));

        return $validator->validate();
    }

    private function validateScope(Request $request, int $subscriptionId): array
    {
        $validator = Validator::make($request->all(), $this->scopeRules($subscriptionId));
        $this->addClassRelationshipValidation($validator);

        return $validator->validate();
    }

    private function addLogicalValidation(LaravelValidator $validator, callable $itemsResolver): void
    {
        $validator->after(function (LaravelValidator $validator) use ($itemsResolver) {
            foreach ($itemsResolver() as $index => $item) {
                $label = isset($item['subject_name'])
                    ? $item['subject_name']
                    : 'Subject row ' . ($index + 1);

                $weekly = (int) ($item['weekly_periods'] ?? 0);
                $daily = (int) ($item['max_periods_per_day'] ?? 0);

                if ($daily > 0 && $weekly > ($daily * 6)) {
                    $validator->errors()->add(
                        "items.$index.weekly_periods",
                        "$label exceeds the six-day capacity allowed by Max/Day."
                    );
                }

                if (!empty($item['prefer_double_period']) && $weekly < 2) {
                    $validator->errors()->add(
                        "items.$index.prefer_double_period",
                        "$label needs at least two weekly periods for double-period preference."
                    );
                }

                if (!empty($item['is_parallel_subject']) && blank($item['parallel_group_code'] ?? null)) {
                    $validator->errors()->add(
                        "items.$index.parallel_group_code",
                        "$label requires a parallel group code."
                    );
                }
            }
        });
    }

    private function addClassRelationshipValidation(LaravelValidator $validator, string $prefix = ''): void
    {
        $validator->after(function (LaravelValidator $validator) use ($prefix) {
            $data = $validator->getData();
            $gradeId = isset($data[$prefix . 'grade_id']) ? (int) $data[$prefix . 'grade_id'] : null;
            $sectionId = isset($data[$prefix . 'section_id']) ? (int) $data[$prefix . 'section_id'] : null;
            $streamId = isset($data[$prefix . 'stream_id']) ? (int) $data[$prefix . 'stream_id'] : null;

            if (!$gradeId) {
                return;
            }

            if ($sectionId) {
                $sectionMatches = DB::table('sections')
                    ->where('id', $sectionId)
                    ->where('grade_id', $gradeId)
                    ->when($streamId, fn ($query) => $query->where(function ($streamQuery) use ($streamId) {
                        $streamQuery->whereNull('stream_id')->orWhere('stream_id', $streamId);
                    }))
                    ->exists();

                if (!$sectionMatches) {
                    $validator->errors()->add(
                        $prefix . 'section_id',
                        'The selected section does not belong to the selected grade or stream.'
                    );
                }
            }

            if ($streamId) {
                $streamMatches = DB::table('subjects')
                    ->where('grade_id', $gradeId)
                    ->where('stream_id', $streamId)
                    ->exists();

                if (!$streamMatches) {
                    $validator->errors()->add(
                        $prefix . 'stream_id',
                        'The selected stream is not configured for the selected grade.'
                    );
                }
            }
        });
    }

    private function scopeRules(int $subscriptionId): array
    {
        return [
            'academic_year_id' => ['nullable', 'integer', $this->ownedExists('academic_years', $subscriptionId)],
            'grade_id' => ['required', 'integer', $this->sharedExists('grades')],
            'section_id' => ['nullable', 'integer', $this->sharedExists('sections')],
            'stream_id' => ['nullable', 'integer', $this->sharedExists('streams')],
        ];
    }

    private function itemRules(int $subscriptionId): array
    {
        return [
            'subject_id' => ['required', 'integer', $this->ownedExists('subjects', $subscriptionId)],
            'preferred_teacher_id' => ['nullable', 'integer', $this->ownedExists('users', $subscriptionId)],
            'subject_category' => ['required', Rule::in(self::CATEGORIES)],
            'weekly_periods' => ['required', 'integer', 'min:0', 'max:60'],
            'max_periods_per_day' => ['required', 'integer', 'min:1', 'max:10'],
            'prefer_double_period' => ['sometimes', 'boolean'],
            'prefer_morning' => ['sometimes', 'boolean'],
            'prefer_last_period' => ['sometimes', 'boolean'],
            'prefer_saturday' => ['sometimes', 'boolean'],
            'is_optional' => ['sometimes', 'boolean'],
            'is_parallel_subject' => ['sometimes', 'boolean'],
            'parallel_group_code' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function identityAttributes(array $data, int $subscriptionId): array
    {
        return [
            'subscription_id' => $subscriptionId,
            'academic_year_id' => $data['academic_year_id'] ?? null,
            'grade_id' => $data['grade_id'],
            'section_id' => $data['section_id'] ?? null,
            'stream_id' => $data['stream_id'] ?? null,
            'subject_id' => $data['subject_id'],
        ];
    }

    private function normaliseItem(array $item): array
    {
        $item['parallel_group_code'] = !empty($item['is_parallel_subject'])
            ? trim((string) ($item['parallel_group_code'] ?? ''))
            : null;

        $item['priority'] = $item['priority'] ?? 5;
        $item['is_active'] = $item['is_active'] ?? true;

        return $item;
    }

    private function scopeQuery(
        Builder $query,
        int $subscriptionId,
        ?int $academicYearId,
        int $gradeId,
        ?int $sectionId,
        ?int $streamId
    ): Builder {
        return $query
            ->where('subscription_id', $subscriptionId)
            ->where('academic_year_id', $academicYearId)
            ->where('grade_id', $gradeId)
            ->where('section_id', $sectionId)
            ->where('stream_id', $streamId);
    }

    private function ownedExists(string $table, int $subscriptionId)
    {
        return Rule::exists($table, 'id')->where(
            fn ($query) => $query->where('subscription_id', $subscriptionId)
        );
    }

    private function sharedExists(string $table)
    {
        return Rule::exists($table, 'id');
    }

    private function ensureOwned(SubjectPeriodAllocation $allocation): void
    {
        abort_unless(
            (int) $allocation->subscription_id === $this->subscriptionId(),
            404
        );
    }

    private function subscriptionId(): int
    {
        return (int) auth()->user()->subscription_id;
    }
}

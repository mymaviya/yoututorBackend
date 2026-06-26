<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionBankPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class QuestionBankPackageController extends Controller
{
    public function index(Request $request)
    {
        $query = QuestionBankPackage::with([
            'grades.grade',
            'grades.stream',
        ]);

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('package_type')) {
            $query->where('package_type', $request->package_type);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('slug', 'like', "%{$request->search}%")
                    ->orWhere('grade_group', 'like', "%{$request->search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query
                ->orderBy('sort_order')
                ->orderBy('id')
                ->paginate((int) $request->input('per_page', 20)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        return DB::transaction(function () use ($data) {
            $package = QuestionBankPackage::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?: Str::slug($data['name']),
                'package_type' => $data['package_type'],
                'grade_group' => $data['grade_group'] ?? null,
                'price' => $data['price'],
                'validity_days' => $data['validity_days'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->syncGrades($package, $data['grades'] ?? []);

            return response()->json([
                'success' => true,
                'message' => 'Question bank package created successfully.',
                'data' => $package->load(['grades.grade', 'grades.stream']),
            ], 201);
        });
    }

    public function show(QuestionBankPackage $questionBankPackage)
    {
        return response()->json([
            'success' => true,
            'data' => $questionBankPackage->load([
                'grades.grade',
                'grades.stream',
                'masterQuestions.grade',
                'masterQuestions.stream',
                'masterQuestions.subject',
                'masterQuestions.lesson',
                'masterQuestions.type',
            ]),
        ]);
    }

    public function update(Request $request, QuestionBankPackage $questionBankPackage)
    {
        $data = $this->validatedData($request, $questionBankPackage->id);

        return DB::transaction(function () use ($questionBankPackage, $data) {
            $questionBankPackage->update([
                'name' => $data['name'],
                'slug' => $data['slug'] ?: Str::slug($data['name']),
                'package_type' => $data['package_type'],
                'grade_group' => $data['grade_group'] ?? null,
                'price' => $data['price'],
                'validity_days' => $data['validity_days'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->syncGrades($questionBankPackage, $data['grades'] ?? []);

            return response()->json([
                'success' => true,
                'message' => 'Question bank package updated successfully.',
                'data' => $questionBankPackage->fresh()->load(['grades.grade', 'grades.stream']),
            ]);
        });
    }

    public function destroy(QuestionBankPackage $questionBankPackage)
    {
        if ($questionBankPackage->purchases()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This package has purchases and cannot be deleted. You can deactivate it instead.',
            ], 422);
        }

        $questionBankPackage->delete();

        return response()->json([
            'success' => true,
            'message' => 'Question bank package deleted successfully.',
        ]);
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('question_bank_packages', 'slug')->ignore($ignoreId),
            ],
            'package_type' => ['required', Rule::in([
                'single_grade',
                'grade_group',
                'stream_group',
            ])],
            'grade_group' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'validity_days' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            'grades' => ['nullable', 'array'],
            'grades.*.grade_id' => ['required_with:grades', 'exists:grades,id'],
            'grades.*.stream_id' => ['nullable', 'exists:streams,id'],
        ]);
    }

    private function syncGrades(QuestionBankPackage $package, array $grades): void
    {
        $package->grades()->delete();

        foreach ($grades as $item) {
            $package->grades()->create([
                'grade_id' => $item['grade_id'],
                'stream_id' => $item['stream_id'] ?? null,
            ]);
        }
    }
}
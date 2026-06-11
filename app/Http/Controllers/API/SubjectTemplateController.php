<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\SubjectTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SubjectTemplateController extends Controller
{
    public function index()
    {
        return SubjectTemplate::with('items')
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:subject_templates,name'],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        return DB::transaction(function () use ($validated) {
            $template = SubjectTemplate::create([
                'name' => $validated['name'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            foreach ($validated['subjects'] as $subject) {
                $template->items()->create([
                    'subject_name' => trim($subject),
                ]);
            }

            return $template->load('items');
        });
    }

    public function update(Request $request, SubjectTemplate $subjectTemplate)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subject_templates', 'name')->ignore($subjectTemplate->id),
            ],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        return DB::transaction(function () use ($validated, $subjectTemplate) {
            $subjectTemplate->update([
                'name' => $validated['name'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $subjectTemplate->items()->delete();

            foreach ($validated['subjects'] as $subject) {
                $subjectTemplate->items()->create([
                    'subject_name' => trim($subject),
                ]);
            }

            return $subjectTemplate->load('items');
        });
    }

    public function destroy(SubjectTemplate $subjectTemplate)
    {
        $subjectTemplate->delete();

        return response()->json([
            'message' => 'Subject template deleted successfully',
        ]);
    }

    public function apply(Request $request, SubjectTemplate $subjectTemplate)
    {
        $validated = $request->validate([
            'grade_ids' => ['required', 'array', 'min:1'],
            'grade_ids.*' => ['required', 'exists:grades,id'],
            'stream_id' => ['nullable', 'exists:streams,id'],
        ]);

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($subjectTemplate, $validated, &$created, &$skipped) {
            $subjectTemplate->load('items');

            foreach ($validated['grade_ids'] as $gradeId) {
                foreach ($subjectTemplate->items as $item) {
                    $exists = Subject::where('grade_id', $gradeId)
                        ->where('stream_id', $validated['stream_id'] ?? null)
                        ->whereRaw('LOWER(name) = ?', [strtolower($item->subject_name)])
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    Subject::create([
                        'grade_id' => $gradeId,
                        'stream_id' => $validated['stream_id'] ?? null,
                        'name' => $item->subject_name,
                        'is_active' => true,
                    ]);

                    $created++;
                }
            }
        });

        return response()->json([
            'message' => 'Template applied successfully',
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }
}
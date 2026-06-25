<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Subject;
use Illuminate\Http\Request;
use App\Imports\LessonImport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Schema;

class LessonController extends Controller
{
    private function isSuperAdmin(): bool
    {
        $user = auth()->user();
        $role = $user?->roleData?->slug ?? $user?->role;

        return in_array($role, ['superadmin', 'super_admin'], true);
    }

    private function applyTenantScope($query, string $table)
    {
        if (! $this->isSuperAdmin() && Schema::hasColumn($table, 'subscription_id')) {
            $query->where($table . '.subscription_id', auth()->user()?->subscription_id);
        }

        return $query;
    }

    private function addTenantId(array $data, string $table): array
    {
        if (Schema::hasColumn($table, 'subscription_id')) {
            $data['subscription_id'] = auth()->user()?->subscription_id;
        }

        return $data;
    }

    public function index(Request $request)
    {
        $query = $this->applyTenantScope(
            Lesson::with(['subject', 'subject.grade', 'subject.stream']),
            'lessons'
        );

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('grade_id')) {
            $query->where('grade_id', $request->grade_id);
        }

        if ($request->filled('stream_id')) {
            $streamId = $request->stream_id;

            $query->where(function ($q) use ($streamId) {
                $q->whereNull('stream_id')
                    ->orWhere('stream_id', $streamId);
            });
        }

        return $query
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'grade_id' => 'required|exists:grades,id',
            'stream_id' => 'nullable|exists:streams,id',
            'subject_id' => 'required|exists:subjects,id',
            'name' => 'required|string|max:255',
            'genre' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $subject = $this->applyTenantScope(Subject::where('id', $data['subject_id']), 'subjects')
            ->where('grade_id', $data['grade_id'])
            ->first();

        if (!$subject) {
            return response()->json([
                'message' => 'Selected subject does not belong to the selected class.',
                'errors' => [
                    'subject_id' => ['Selected subject does not belong to the selected class.'],
                ],
            ], 422);
        }

        $data['stream_id'] = $subject->stream_id ?? ($data['stream_id'] ?? null);
        $data['is_active'] = $request->boolean('is_active', true);

        $exists = $this->applyTenantScope(Lesson::where('subject_id', $data['subject_id']), 'lessons')
            ->whereRaw('LOWER(name) = ?', [strtolower($data['name'])])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This lesson already exists for the selected subject.',
                'errors' => [
                    'name' => ['This lesson already exists for the selected subject.'],
                ],
            ], 422);
        }

        $lesson = Lesson::create($this->addTenantId($data, 'lessons'));

        return response()->json(
            $lesson->load(['subject', 'subject.grade', 'subject.stream']),
            201
        );
    }

    public function show(string $id)
    {
        return $this->applyTenantScope(
            Lesson::with(['subject', 'subject.grade', 'subject.stream']),
            'lessons'
        )->findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        $lesson = $this->applyTenantScope(Lesson::query(), 'lessons')->findOrFail($id);

        $data = $request->validate([
            'grade_id' => 'required|exists:grades,id',
            'stream_id' => 'nullable|exists:streams,id',
            'subject_id' => 'required|exists:subjects,id',
            'name' => 'required|string|max:255',
            'genre' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $subject = $this->applyTenantScope(Subject::where('id', $data['subject_id']), 'subjects')
            ->where('grade_id', $data['grade_id'])
            ->first();

        if (!$subject) {
            return response()->json([
                'message' => 'Selected subject does not belong to the selected class.',
                'errors' => [
                    'subject_id' => ['Selected subject does not belong to the selected class.'],
                ],
            ], 422);
        }

        $data['stream_id'] = $subject->stream_id ?? ($data['stream_id'] ?? null);

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $exists = $this->applyTenantScope(Lesson::where('subject_id', $data['subject_id']), 'lessons')
            ->whereRaw('LOWER(name) = ?', [strtolower($data['name'])])
            ->where('id', '!=', $lesson->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This lesson already exists for the selected subject.',
                'errors' => [
                    'name' => ['This lesson already exists for the selected subject.'],
                ],
            ], 422);
        }

        $lesson->update($this->addTenantId($data, 'lessons'));

        return response()->json(
            $lesson->fresh()->load(['subject', 'subject.grade', 'subject.stream'])
        );
    }

    public function destroy(string $id)
    {
        $this->applyTenantScope(Lesson::query(), 'lessons')->findOrFail($id)->delete();

        return response()->json([
            'message' => 'Deleted',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $import = new LessonImport();

        Excel::import($import, $request->file('file'));

        return response()->json([
            'message' => 'Lesson import completed.',
            'created' => $import->created,
            'skipped' => $import->skipped,
            'errors' => $import->errors,
        ]);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $headers = [
            'Grade',
            'Stream',
            'Subject',
            'Lesson Name',
            'Genre',
        ];

        $rows = [
            $headers,
            ['Grade 11', 'Science', 'English Core (301)', 'The Portrait of a Lady', 'Prose'],
            ['Grade 11', 'Science', 'English Core (301)', 'A Photograph', 'Poetry'],
            ['Grade 11', 'Science', 'Physics (042)', 'Units and Measurements', 'Theory'],
        ];

        $filename = 'lesson_import_template.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}

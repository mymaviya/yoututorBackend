<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Subject;
use Illuminate\Http\Request;
use App\Imports\LessonImport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LessonController extends Controller
{
    public function index(Request $request)
    {
        $query = Lesson::with(['subject', 'subject.grade', 'subject.stream']);

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

        $subject = Subject::where('id', $data['subject_id'])
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

        $exists = Lesson::where('subject_id', $data['subject_id'])
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

        $lesson = Lesson::create($data);

        return response()->json(
            $lesson->load(['subject', 'subject.grade', 'subject.stream']),
            201
        );
    }

    public function show(string $id)
    {
        return Lesson::with(['subject', 'subject.grade', 'subject.stream'])->findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        $lesson = Lesson::findOrFail($id);

        $data = $request->validate([
            'grade_id' => 'required|exists:grades,id',
            'stream_id' => 'nullable|exists:streams,id',
            'subject_id' => 'required|exists:subjects,id',
            'name' => 'required|string|max:255',
            'genre' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $subject = Subject::where('id', $data['subject_id'])
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

        $exists = Lesson::where('subject_id', $data['subject_id'])
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

        $lesson->update($data);

        return response()->json(
            $lesson->fresh()->load(['subject', 'subject.grade', 'subject.stream'])
        );
    }

    public function destroy(string $id)
    {
        Lesson::findOrFail($id)->delete();

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

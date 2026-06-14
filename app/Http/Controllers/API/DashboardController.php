<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ExamPortion;
use App\Models\Grade;
use App\Models\Question;
use App\Models\QuestionPaper;
use App\Models\Subject;
use App\Models\TeacherQuestionTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private function teachers()
    {
        return User::query()
            ->where(function ($q) {
                $q->where('role', 'teacher')
                    ->orWhereHas('roleData', fn($role) => $role->where('slug', 'teacher'));
            });
    }

    public function index()
    {
        $teachers = $this->teachers()->with(['teacherAssignments.grade', 'teacherAssignments.stream', 'teacherAssignments.subject'])->get();

        return response()->json([
            'summary' => [
                'teachers' => $teachers->count(),
                'grades' => Grade::count(),
                'subjects' => Subject::count(),
                'questions' => Question::count(),
                'approved_questions' => Question::where('status', 'approved')->count(),
                'pending_questions' => Question::where('status', 'pending')->count(),
                'papers' => QuestionPaper::count(),
                'published_papers' => QuestionPaper::where('status', 'published')->count(),
                'draft_papers' => QuestionPaper::where('status', 'draft')->count(),
                'teacher_tasks' => TeacherQuestionTask::count(),
                'exam_portions' => ExamPortion::count(),
            ],
            'recent_questions' => Question::with(['grade', 'stream', 'subject', 'lesson', 'type', 'creator'])->latest()->take(6)->get(),
            'recent_papers' => QuestionPaper::with(['grade', 'stream', 'subject', 'creator'])->latest()->take(6)->get(),
            'analytics' => [
                'question_status' => Question::select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->get(),
                'bloom_levels' => Question::select('bloom_level', DB::raw('COUNT(*) as total'))->whereNotNull('bloom_level')->groupBy('bloom_level')->get(),
                'exam_portion_status' => ExamPortion::select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->get(),
                'questions_by_grade' => Question::join('grades', 'questions.grade_id', '=', 'grades.id')->select('grades.name as grade_name', DB::raw('COUNT(*) as total'))->groupBy('grades.id', 'grades.name')->get(),
                'questions_by_subject' => Question::join('subjects', 'questions.subject_id', '=', 'subjects.id')->select('subjects.name as subject_name', DB::raw('COUNT(*) as total'))->groupBy('subjects.id', 'subjects.name')->get(),
                'teacher_performance' => $teachers->map(fn($teacher) => [
                    'name' => $teacher->name,
                    'questions_count' => Question::where('created_by', $teacher->id)->count(),
                    'approved_questions' => Question::where('created_by', $teacher->id)->where('status', 'approved')->count(),
                    'exam_portions_submitted' => ExamPortion::where('teacher_id', $teacher->id)->whereIn('status', ['submitted', 'approved'])->count(),
                ])->sortByDesc('questions_count')->values()->take(10),
            ],
            'recent_exam_portions' => ExamPortion::with(['teacher', 'grade', 'stream', 'subject', 'examName'])
                ->latest()
                ->take(6)
                ->get(),
            'teacher_progress' => $teachers->map(fn($teacher) => [
                'teacher_id' => $teacher->id,
                'name' => $teacher->name,
                'contact' => $teacher->contact,
                'assignments_count' => $teacher->teacherAssignments->count(),
                'questions_count' => Question::where('created_by', $teacher->id)->count(),
                'papers_count' => QuestionPaper::where('created_by', $teacher->id)->count(),
            ])->take(8)->values(),
        ]);
    }
}

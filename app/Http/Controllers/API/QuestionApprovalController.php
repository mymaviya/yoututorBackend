<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;
use App\Services\AuditService;

class QuestionApprovalController extends Controller
{
    public function index(Request $request)
    {
        $query = Question::with([
            'grade',
            'subject',
            'lesson',
            'options',
            'matchPairs',
            'questionType',
            'languageItems',
            'creator',
            'approver'
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('grade_id')) {
            $query->where('grade_id', $request->grade_id);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        if ($request->filled('search')) {
            $query->where('question', 'like', '%' . $request->search . '%');
        }

        return $query
            ->latest()
            ->paginate(20);
    }

    public function approve(Question $question)
    {
        $question->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        AuditService::log('Questions','Approve','Question approved ID: ' . $question->id);

        notifyUser(
            $question->created_by,
            'Question Approved',
            'Your question has been approved by admin.',
            'question_approved',
            '/questions'
        );

        return response()->json([
            'message' => 'Question approved successfully',
            'data' => $question->load([
                'grade',
                'subject',
                'lesson',
                'options',
                'creator',
                'approver'
            ])
        ]);
    }

    public function reject(Request $request, Question $question)
    {
        $data = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $question->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        AuditService::log('Questions','Reject','Question rejected ID: ' . $question->id);


        notifyUser(
            $question->created_by,
            'Question Rejected',
            $data['rejection_reason'],
            'question_rejected',
            '/questions'
        );

        return response()->json([
            'message' => 'Question rejected successfully',
            'data' => $question->load([
                'grade',
                'subject',
                'lesson',
                'options',
                'creator',
                'approver'
            ])
        ]);
    }
}

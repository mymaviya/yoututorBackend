<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterQuestion;
use App\Models\Question;
use App\Models\SubscriptionQuestionBankPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterQuestionBankController extends Controller
{
    private function subscriptionId(): ?int
    {
        return auth()->user()?->subscription_id;
    }

    private function activePackageIds()
    {
        return SubscriptionQuestionBankPurchase::where('subscription_id', $this->subscriptionId())
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->pluck('question_bank_package_id');
    }

    public function packages()
    {
        $purchases = SubscriptionQuestionBankPurchase::with([
            'package.grades.grade',
            'package.grades.stream',
        ])
            ->where('subscription_id', $this->subscriptionId())
            ->where('status', 'active')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $purchases,
        ]);
    }

    public function questions(Request $request)
    {
        $packageIds = $this->activePackageIds();

        $query = MasterQuestion::with([
            'package',
            'grade',
            'stream',
            'subject',
            'lesson',
            'type',
        ])
            ->whereIn('question_bank_package_id', $packageIds)
            ->where('is_active', true);

        if ($request->filled('question_bank_package_id')) {
            $query->where('question_bank_package_id', $request->question_bank_package_id);
        }

        if ($request->filled('grade_id')) {
            $query->where('grade_id', $request->grade_id);
        }

        if ($request->filled('stream_id')) {
            $query->where('stream_id', $request->stream_id);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('lesson_id')) {
            $query->where('lesson_id', $request->lesson_id);
        }

        if ($request->filled('question_type_master_id')) {
            $query->where('question_type_master_id', $request->question_type_master_id);
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        if ($request->filled('bloom_level')) {
            $query->where('bloom_level', $request->bloom_level);
        }

        if ($request->filled('search')) {
            $query->where('question', 'like', '%' . $request->search . '%');
        }

        return response()->json([
            'success' => true,
            'data' => $query
                ->latest()
                ->paginate((int) $request->input('per_page', 20)),
        ]);
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'master_question_ids' => ['required', 'array', 'min:1'],
            'master_question_ids.*' => ['required', 'exists:master_questions,id'],
        ]);

        $subscriptionId = $this->subscriptionId();

        if (!$subscriptionId) {
            return response()->json([
                'message' => 'No subscription assigned to your account.',
            ], 403);
        }

        $packageIds = $this->activePackageIds();

        $masterQuestions = MasterQuestion::whereIn('id', $data['master_question_ids'])
            ->whereIn('question_bank_package_id', $packageIds)
            ->where('is_active', true)
            ->get();

        if ($masterQuestions->count() !== count($data['master_question_ids'])) {
            return response()->json([
                'message' => 'Some selected questions are not available in your purchased packages.',
            ], 403);
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($masterQuestions, $subscriptionId, &$created, &$skipped) {
            foreach ($masterQuestions as $masterQuestion) {
                $exists = Question::where('subscription_id', $subscriptionId)
                    ->where('grade_id', $masterQuestion->grade_id)
                    ->where('stream_id', $masterQuestion->stream_id)
                    ->where('subject_id', $masterQuestion->subject_id)
                    ->where('lesson_id', $masterQuestion->lesson_id)
                    ->where('question_type_master_id', $masterQuestion->question_type_master_id)
                    ->where('question', $masterQuestion->question)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                Question::create([
                    'subscription_id' => $subscriptionId,
                    'grade_id' => $masterQuestion->grade_id,
                    'stream_id' => $masterQuestion->stream_id,
                    'subject_id' => $masterQuestion->subject_id,
                    'lesson_id' => $masterQuestion->lesson_id,
                    'question_type_master_id' => $masterQuestion->question_type_master_id,
                    'question' => $masterQuestion->question,
                    'difficulty' => $masterQuestion->difficulty,
                    'bloom_level' => $masterQuestion->bloom_level,
                    'marks' => $masterQuestion->marks,
                    'answer' => $masterQuestion->answer,
                    'explanation' => $masterQuestion->explanation,
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'created_by' => auth()->id(),
                    'is_active' => true,
                ]);

                $created++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Questions imported into your question bank.',
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }
}
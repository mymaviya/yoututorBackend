<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterQuestion;
use App\Models\Question;
use App\Models\QuestionBankPackage;
use App\Models\QuestionImage;
use App\Models\QuestionMatchPair;
use App\Models\QuestionOption;
use App\Models\SubscriptionQuestionBankPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MasterQuestionBankController extends Controller
{
    private function subscriptionId(): ?int
    {
        return auth()->user()?->subscription_id;
    }

    private function isSuperAdmin(): bool
    {
        $user = auth()->user();
        $role = $user?->roleData?->slug ?? $user?->role;

        return in_array($role, ['superadmin', 'super_admin'], true);
    }

    private function activePackageIds(): Collection
    {
        $subscription = auth()->user()?->subscription;

        if (! $subscription) {
            return collect();
        }

        $planPackageIds = $subscription->plan?->questionBankPackages()
            ->where('question_bank_packages.is_active', true)
            ->pluck('question_bank_packages.id') ?? collect();

        $manualPurchaseIds = SubscriptionQuestionBankPurchase::where('subscription_id', $subscription->id)
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

        return $planPackageIds
            ->merge($manualPurchaseIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function packageSources(Collection $packageIds): Collection
    {
        $subscription = auth()->user()?->subscription;

        if (! $subscription || $packageIds->isEmpty()) {
            return collect();
        }

        $planPackageIds = $subscription->plan?->questionBankPackages()
            ->whereIn('question_bank_packages.id', $packageIds)
            ->pluck('question_bank_packages.id')
            ->map(fn ($id) => (int) $id) ?? collect();

        $manualPackageIds = SubscriptionQuestionBankPurchase::where('subscription_id', $subscription->id)
            ->whereIn('question_bank_package_id', $packageIds)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->pluck('question_bank_package_id')
            ->map(fn ($id) => (int) $id);

        return $packageIds->mapWithKeys(function ($packageId) use ($planPackageIds, $manualPackageIds) {
            $sources = [];

            if ($planPackageIds->contains((int) $packageId)) {
                $sources[] = 'subscription_plan';
            }

            if ($manualPackageIds->contains((int) $packageId)) {
                $sources[] = 'manual_purchase';
            }

            return [(int) $packageId => $sources ?: ['unknown']];
        });
    }

    private function sourceLabel(array $sources): string
    {
        if (in_array('subscription_plan', $sources, true) && in_array('manual_purchase', $sources, true)) {
            return 'Included in Plan + Add-on Purchase';
        }

        if (in_array('subscription_plan', $sources, true)) {
            return 'Included in Plan';
        }

        if (in_array('manual_purchase', $sources, true)) {
            return 'Add-on Purchase';
        }

        if (in_array('superadmin_preview', $sources, true)) {
            return 'Super Admin Preview';
        }

        return 'Available';
    }

    private function masterQuestionRelations(): array
    {
        return [
            'package',
            'grade',
            'stream',
            'subject' => fn ($q) => $q->withoutGlobalScopes(),
            'lesson' => fn ($q) => $q->withoutGlobalScopes(),
            'type',
            'options',
            'matchPairs',
            'images',
        ];
    }

    public function packages()
    {
        if ($this->isSuperAdmin()) {
            $packages = QuestionBankPackage::with([
                'grades.grade',
                'grades.stream',
            ])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $packages->map(fn ($package) => [
                    'id' => null,
                    'source' => 'superadmin_preview',
                    'sources' => ['superadmin_preview'],
                    'source_label' => 'Super Admin Preview',
                    'package' => $package,
                ])->values(),
            ]);
        }

        $subscription = auth()->user()?->subscription;

        if (! $subscription) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $packageIds = $this->activePackageIds();

        if ($packageIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $sourceMap = $this->packageSources($packageIds);

        $packages = QuestionBankPackage::with([
            'grades.grade',
            'grades.stream',
        ])
            ->whereIn('id', $packageIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $packages->map(function ($package) use ($sourceMap) {
                $sources = $sourceMap->get((int) $package->id, ['unknown']);

                return [
                    'id' => null,
                    'source' => implode(',', $sources),
                    'sources' => $sources,
                    'source_label' => $this->sourceLabel($sources),
                    'package' => $package,
                ];
            })->values(),
        ]);
    }

    public function questions(Request $request)
    {
        if ($this->isSuperAdmin()) {
            $query = MasterQuestion::with($this->masterQuestionRelations())
                ->where('is_active', true);
        } else {
            $packageIds = $this->activePackageIds();

            if ($packageIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'data' => [],
                        'total' => 0,
                        'current_page' => 1,
                        'per_page' => (int) $request->input('per_page', 20),
                    ],
                ]);
            }

            if ($request->filled('question_bank_package_id') && ! $packageIds->contains((int) $request->question_bank_package_id)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'data' => [],
                        'total' => 0,
                        'current_page' => 1,
                        'per_page' => (int) $request->input('per_page', 20),
                    ],
                ]);
            }

            $query = MasterQuestion::with($this->masterQuestionRelations())
                ->whereIn('question_bank_package_id', $packageIds)
                ->where('is_active', true);
        }

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
        if ($this->isSuperAdmin()) {
            return response()->json([
                'message' => 'Super admin preview mode cannot import questions into a school question bank.',
            ], 403);
        }

        $data = $request->validate([
            'master_question_ids' => ['required', 'array', 'min:1'],
            'master_question_ids.*' => ['required', 'exists:master_questions,id'],
        ]);

        $subscriptionId = $this->subscriptionId();

        if (! $subscriptionId) {
            return response()->json([
                'message' => 'No subscription assigned to your account.',
            ], 403);
        }

        $packageIds = $this->activePackageIds();

        $masterQuestions = MasterQuestion::with([
            'options',
            'matchPairs',
            'images',
        ])
            ->whereIn('id', $data['master_question_ids'])
            ->whereIn('question_bank_package_id', $packageIds)
            ->where('is_active', true)
            ->get();

        if ($masterQuestions->count() !== count($data['master_question_ids'])) {
            return response()->json([
                'message' => 'Some selected questions are not available in your subscription packages.',
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

                $question = Question::create([
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

                foreach ($masterQuestion->options as $option) {
                    QuestionOption::create([
                        'question_id' => $question->id,
                        'option_text' => $option->option_text,
                        'option_image' => $option->option_image,
                        'is_correct' => $option->is_correct,
                        'sort_order' => $option->sort_order,
                    ]);
                }

                foreach ($masterQuestion->matchPairs as $pair) {
                    QuestionMatchPair::create([
                        'question_id' => $question->id,
                        'left_value' => $pair->left_value,
                        'right_value' => $pair->right_value,
                        'sort_order' => $pair->sort_order,
                    ]);
                }

                foreach ($masterQuestion->images as $image) {
                    QuestionImage::create([
                        'question_id' => $question->id,
                        'image_path' => $image->image_path,
                    ]);
                }

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

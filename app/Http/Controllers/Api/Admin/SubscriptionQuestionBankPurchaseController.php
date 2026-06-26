<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionBankPackage;
use App\Models\Subscription;
use App\Models\SubscriptionQuestionBankPurchase;
use Illuminate\Http\Request;

class SubscriptionQuestionBankPurchaseController extends Controller
{
    public function index(Request $request)
    {
        $query = SubscriptionQuestionBankPurchase::with([
            'subscription',
            'package',
            'creator',
        ]);

        if ($request->filled('subscription_id')) {
            $query->where('subscription_id', $request->subscription_id);
        }

        if ($request->filled('question_bank_package_id')) {
            $query->where('question_bank_package_id', $request->question_bank_package_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data' => $query
                ->latest()
                ->paginate((int) $request->input('per_page', 20)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subscription_id' => ['required', 'exists:subscriptions,id'],
            'question_bank_package_id' => ['required', 'exists:question_bank_packages,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['nullable', 'in:pending,active,expired,cancelled'],
        ]);

        $subscription = Subscription::findOrFail($data['subscription_id']);
        $package = QuestionBankPackage::findOrFail($data['question_bank_package_id']);

        $startsAt = $data['starts_at'] ?? now();

        $endsAt = $data['ends_at'] ?? (
            $package->validity_days
                ? now()->addDays($package->validity_days)
                : null
        );

        $purchase = SubscriptionQuestionBankPurchase::updateOrCreate(
            [
                'subscription_id' => $subscription->id,
                'question_bank_package_id' => $package->id,
            ],
            [
                'amount' => $data['amount'] ?? $package->price,
                'status' => $data['status'] ?? 'active',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'created_by' => auth()->id(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Question bank package access assigned successfully.',
            'data' => $purchase->load([
                'subscription',
                'package',
                'creator',
            ]),
        ], 201);
    }

    public function show(SubscriptionQuestionBankPurchase $questionBankPurchase)
    {
        return response()->json([
            'success' => true,
            'data' => $questionBankPurchase->load([
                'subscription',
                'package',
                'creator',
            ]),
        ]);
    }

    public function update(Request $request, SubscriptionQuestionBankPurchase $questionBankPurchase)
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', 'in:pending,active,expired,cancelled'],
        ]);

        $questionBankPurchase->update([
            'amount' => $data['amount'] ?? $questionBankPurchase->amount,
            'starts_at' => $data['starts_at'] ?? $questionBankPurchase->starts_at,
            'ends_at' => $data['ends_at'] ?? $questionBankPurchase->ends_at,
            'status' => $data['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Question bank package access updated successfully.',
            'data' => $questionBankPurchase->fresh()->load([
                'subscription',
                'package',
                'creator',
            ]),
        ]);
    }

    public function destroy(SubscriptionQuestionBankPurchase $questionBankPurchase)
    {
        $questionBankPurchase->delete();

        return response()->json([
            'success' => true,
            'message' => 'Question bank package access removed successfully.',
        ]);
    }
}
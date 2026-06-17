<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LicenseKey;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRenewal;
use App\Mail\RenewalSuccessfulMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionRenewalController extends Controller
{
    public function index(Request $request)
    {
        $query = SubscriptionRenewal::with([
            'subscription',
            'plan',
            'paymentTransaction',
            'renewedBy',
        ]);

        if ($request->filled('subscription_id')) {
            $query->where('subscription_id', $request->subscription_id);
        }

        if ($request->filled('renewal_type')) {
            $query->where('renewal_type', $request->renewal_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->whereHas('subscription', function ($q) use ($search) {
                $q->where('school_name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $renewals = $query
            ->latest()
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $renewals,
        ]);
    }

    public function renew(Request $request, Subscription $subscription)
    {
        $validated = $request->validate([
            'subscription_plan_id' => 'nullable|exists:subscription_plans,id',
            'duration_days' => 'nullable|integer|min:1|max:36500',
            'renewal_amount' => 'nullable|numeric|min:0',
            'renewal_type' => 'required|in:renewal,upgrade,downgrade,trial_conversion,manual_extension',
            'remarks' => 'nullable|string|max:2000',
        ]);

        return DB::transaction(function () use ($subscription, $validated) {
            $plan = !empty($validated['subscription_plan_id'])
                ? SubscriptionPlan::findOrFail($validated['subscription_plan_id'])
                : $subscription->plan;

            $durationDays = (int) (
                $validated['duration_days']
                ?? $plan?->duration_days
                ?? 365
            );

            $oldStart = $subscription->starts_at;
            $oldEnd = $subscription->ends_at;
            $oldAmount = $subscription->amount ?? 0;

            $baseDate = $subscription->ends_at && $subscription->ends_at->greaterThan(now())
                ? $subscription->ends_at->copy()
                : now();

            $newStart = now();
            $newEnd = $baseDate->copy()->addDays($durationDays);

            $renewalAmount = $validated['renewal_amount']
                ?? $plan?->yearly_price
                ?? $subscription->amount
                ?? 0;

            $subscription->update([
                'subscription_plan_id' => $plan?->id ?? $subscription->subscription_plan_id,
                'status' => 'active',
                'starts_at' => $subscription->starts_at ?: $newStart->toDateString(),
                'ends_at' => $newEnd->toDateString(),
                'amount' => $renewalAmount,
                'is_trial' => false,
            ]);

            if ($subscription->licenseKey) {
                $subscription->licenseKey->update([
                    'status' => 'active',
                    'expires_at' => $newEnd,
                ]);
            } else {
                LicenseKey::create([
                    'subscription_id' => $subscription->id,
                    'license_key' => 'YT-' . strtoupper(\Illuminate\Support\Str::random(20)),
                    'status' => 'active',
                    'activated_at' => now(),
                    'expires_at' => $newEnd,
                ]);
            }

            $renewal = SubscriptionRenewal::create([
                'subscription_id' => $subscription->id,
                'subscription_plan_id' => $plan?->id,
                'payment_transaction_id' => null,
                'old_start_date' => $oldStart,
                'old_end_date' => $oldEnd,
                'new_start_date' => $newStart->toDateString(),
                'new_end_date' => $newEnd->toDateString(),
                'duration_days' => $durationDays,
                'old_amount' => $oldAmount,
                'renewal_amount' => $renewalAmount,
                'renewal_type' => $validated['renewal_type'],
                'remarks' => $validated['remarks'] ?? null,
                'renewed_by' => auth()->id(),
            ]);

            if ($subscription->email) {
                Mail::to($subscription->email)
                    ->queue(new RenewalSuccessfulMail($renewal->load(['subscription','plan'])));
            }

            return response()->json([
                'success' => true,
                'message' => 'Subscription renewed successfully.',
                'data' => [
                    'subscription' => $subscription->load(['plan', 'licenseKey', 'renewals']),
                    'renewal' => $renewal->load(['plan', 'renewedBy']),
                ],
            ]);
        });
    }
}

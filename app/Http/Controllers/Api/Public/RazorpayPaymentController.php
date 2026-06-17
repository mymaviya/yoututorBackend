<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\LicenseKey;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Razorpay\Api\Api;

class RazorpayPaymentController extends Controller
{
    public function createOrder(Request $request)
    {
        $validated = $request->validate([
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'school_name' => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'mobile' => 'required|string|max:20',
            'email' => 'required|email|max:255',
        ]);

        $plan = SubscriptionPlan::where('is_active', true)
            ->findOrFail($validated['subscription_plan_id']);

        if ($plan->is_trial) {
            return response()->json([
                'success' => false,
                'message' => 'Trial plan cannot be purchased.',
            ], 422);
        }

        if ($plan->yearly_price <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid subscription amount.',
            ], 422);
        }

        $api = new Api(
            config('services.razorpay.key_id'),
            config('services.razorpay.key_secret')
        );

        return DB::transaction(function () use ($validated, $plan, $api) {
            $subscription = Subscription::create([
                'subscription_plan_id' => $plan->id,
                'school_name' => $validated['school_name'],
                'contact_person' => $validated['contact_person'],
                'mobile' => $validated['mobile'],
                'email' => $validated['email'],
                'status' => 'pending_payment',
                'amount' => $plan->yearly_price,
                'starts_at' => null,
                'ends_at' => null,
                'is_trial' => false,
                'auto_renew' => false,
            ]);

            $order = $api->order->create([
                'receipt' => 'sub_' . $subscription->id,
                'amount' => (int) round($plan->yearly_price * 100),
                'currency' => 'INR',
                'notes' => [
                    'subscription_id' => (string) $subscription->id,
                    'plan' => $plan->name,
                    'school_name' => $validated['school_name'],
                    'email' => $validated['email'],
                ],
            ]);

            PaymentTransaction::create([
                'subscription_id' => $subscription->id,
                'gateway' => 'razorpay',
                'razorpay_order_id' => $order['id'],
                'amount' => $plan->yearly_price,
                'currency' => 'INR',
                'status' => 'created',
                'gateway_response' => $order->toArray(),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription_id' => $subscription->id,
                    'order_id' => $order['id'],
                    'amount' => (int) round($plan->yearly_price * 100),
                    'currency' => 'INR',
                    'key' => config('services.razorpay.key_id'),
                    'plan' => $plan,
                    'customer' => [
                        'name' => $validated['contact_person'],
                        'email' => $validated['email'],
                        'contact' => $validated['mobile'],
                    ],
                ],
            ]);
        });
    }

    public function verifyPayment(Request $request)
    {
        $validated = $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $transaction = PaymentTransaction::where('razorpay_order_id', $validated['razorpay_order_id'])
            ->where('subscription_id', $validated['subscription_id'])
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Payment transaction not found.',
            ], 404);
        }

        if ($transaction->status === 'paid') {
            $subscription = Subscription::with(['plan', 'licenseKey'])
                ->findOrFail($validated['subscription_id']);

            return response()->json([
                'success' => true,
                'message' => 'Payment already verified.',
                'data' => [
                    'subscription' => $subscription,
                    'license_key' => $subscription->licenseKey,
                ],
            ]);
        }

        $api = new Api(
            config('services.razorpay.key_id'),
            config('services.razorpay.key_secret')
        );

        try {
            $api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $validated['razorpay_order_id'],
                'razorpay_payment_id' => $validated['razorpay_payment_id'],
                'razorpay_signature' => $validated['razorpay_signature'],
            ]);
        } catch (\Exception $e) {
            $transaction->update([
                'razorpay_payment_id' => $validated['razorpay_payment_id'],
                'razorpay_signature' => $validated['razorpay_signature'],
                'status' => 'failed',
                'gateway_response' => array_merge(
                    $transaction->gateway_response ?? [],
                    [
                        'verification_error' => $e->getMessage(),
                    ]
                ),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed.',
            ], 422);
        }

        return DB::transaction(function () use ($validated, $transaction) {
            $subscription = Subscription::with('plan')
                ->lockForUpdate()
                ->findOrFail($validated['subscription_id']);

            if ($subscription->status === 'active' && $subscription->licenseKey) {
                return response()->json([
                    'success' => true,
                    'message' => 'Subscription already active.',
                    'data' => [
                        'subscription' => $subscription->load(['plan', 'licenseKey']),
                        'license_key' => $subscription->licenseKey,
                    ],
                ]);
            }

            $startsAt = now();
            $endsAt = now()->addDays($subscription->plan->duration_days ?: 365);

            $subscription->update([
                'status' => 'active',
                'starts_at' => $startsAt->toDateString(),
                'ends_at' => $endsAt->toDateString(),
                'is_trial' => false,
            ]);

            $transaction->update([
                'razorpay_payment_id' => $validated['razorpay_payment_id'],
                'razorpay_signature' => $validated['razorpay_signature'],
                'status' => 'paid',
                'gateway_response' => array_merge(
                    $transaction->gateway_response ?? [],
                    [
                        'verified_at' => now()->toDateTimeString(),
                    ]
                ),
            ]);

            $licenseKey = LicenseKey::updateOrCreate(
                ['subscription_id' => $subscription->id],
                [
                    'license_key' => $subscription->licenseKey?->license_key
                        ?? 'YT-' . strtoupper(Str::random(20)),
                    'status' => 'active',
                    'activated_at' => now(),
                    'expires_at' => $endsAt,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment verified and subscription activated successfully.',
                'data' => [
                    'subscription' => $subscription->load(['plan', 'licenseKey']),
                    'license_key' => $licenseKey,
                ],
            ]);
        });
    }
}
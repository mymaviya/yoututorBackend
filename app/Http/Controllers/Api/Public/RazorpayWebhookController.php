<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\LicenseKey;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Mail\PaymentReceiptMail;
use Illuminate\Support\Facades\Mail;

class RazorpayWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');
        $secret = config('services.razorpay.webhook_secret');

        if (!$this->verifySignature($payload, $signature, $secret)) {
            Log::warning('Invalid Razorpay webhook signature.');

            return response()->json([
                'success' => false,
                'message' => 'Invalid signature.',
            ], 400);
        }

        $event = $request->input('event');


        return match ($event) {
            'payment.captured' => $this->paymentCaptured($request),
            'payment.failed' => $this->paymentFailed($request),
            default => response()->json([
                'success' => true,
                'message' => 'Webhook received but event ignored.',
                'event' => $event,
            ]),
        };
    }

    private function verifySignature(?string $payload, ?string $signature, ?string $secret): bool
    {
        if (!$payload || !$signature || !$secret) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    private function paymentCaptured(Request $request)
    {
        $payment = $request->input('payload.payment.entity');

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment payload missing.',
            ], 422);
        }

        return DB::transaction(function () use ($payment) {
            $orderId = $payment['order_id'] ?? null;
            $paymentId = $payment['id'] ?? null;

            $transaction = PaymentTransaction::where('razorpay_order_id', $orderId)->first();

            if (!$transaction) {
                Log::warning('Razorpay webhook transaction not found.', [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction not found, webhook ignored.',
                ]);
            }

            if ($transaction->status === 'paid') {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment already processed.',
                ]);
            }

            $subscription = Subscription::with('plan')
                ->lockForUpdate()
                ->find($transaction->subscription_id);

            if (!$subscription) {
                return response()->json([
                    'success' => true,
                    'message' => 'Subscription not found, webhook ignored.',
                ]);
            }

            $startsAt = now();
            $endsAt = now()->addDays((int) ($subscription->plan?->duration_days ?? 365));

            $subscription->update([
                'status' => 'active',
                'starts_at' => $startsAt->toDateString(),
                'ends_at' => $endsAt->toDateString(),
                'is_trial' => false,
            ]);

            $transaction->update([
                'razorpay_payment_id' => $paymentId,
                'status' => 'paid',
                'gateway_response' => array_merge(
                    $transaction->gateway_response ?? [],
                    [
                        'webhook_event' => 'payment.captured',
                        'payment' => $payment,
                        'webhook_processed_at' => now()->toDateTimeString(),
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

            if (
                $transaction->subscription &&
                $transaction->subscription->email
            ) {
                Mail::to($transaction->subscription->email)
                    ->queue(
                        new PaymentReceiptMail($transaction)
                    );
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment captured and subscription activated.',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'license_key_id' => $licenseKey->id,
                ],
            ]);
        });
    }

    private function paymentFailed(Request $request)
    {
        $payment = $request->input('payload.payment.entity');

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment payload missing.',
            ], 422);
        }

        $orderId = $payment['order_id'] ?? null;
        $paymentId = $payment['id'] ?? null;

        $transaction = PaymentTransaction::where('razorpay_order_id', $orderId)->first();

        if ($transaction) {
            $transaction->update([
                'razorpay_payment_id' => $paymentId,
                'status' => 'failed',
                'gateway_response' => array_merge(
                    $transaction->gateway_response ?? [],
                    [
                        'webhook_event' => 'payment.failed',
                        'payment' => $payment,
                        'webhook_processed_at' => now()->toDateTimeString(),
                    ]
                ),
            ]);

            Subscription::where('id', $transaction->subscription_id)
                ->update([
                    'status' => 'pending_payment',
                ]);
        }

        if (
            $transaction->subscription &&
            $transaction->subscription->email
        ) {
            Mail::to($transaction->subscription->email)
                ->queue(
                    new PaymentReceiptMail($transaction)
                );
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment failure processed.',
        ]);
    }
}

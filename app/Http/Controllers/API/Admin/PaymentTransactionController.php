<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;

class PaymentTransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentTransaction::with([
            'subscription.plan',
            'subscription.licenseKey',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('gateway')) {
            $query->where('gateway', $request->gateway);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('razorpay_order_id', 'like', "%{$search}%")
                    ->orWhere('razorpay_payment_id', 'like', "%{$search}%")
                    ->orWhereHas('subscription', function ($subQuery) use ($search) {
                        $subQuery->where('school_name', 'like', "%{$search}%")
                            ->orWhere('contact_person', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $transactions = $query
            ->latest()
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    public function show(PaymentTransaction $paymentTransaction)
    {
        $paymentTransaction->load([
            'subscription.plan',
            'subscription.licenseKey',
        ]);

        return response()->json([
            'success' => true,
            'data' => $paymentTransaction,
        ]);
    }
}
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\LicenseKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Mail\SubscriptionActivatedMail;
use Illuminate\Support\Facades\Mail;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = Subscription::with([
            'plan',
            'licenseKey'
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('school_name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query
                ->latest()
                ->paginate($request->get('per_page', 20))
        ]);
    }

    public function show(Subscription $subscription)
    {
        return response()->json([
            'success' => true,
            'data' => $subscription->load([
                'plan',
                'licenseKey'
            ])
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'school_name' => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'mobile' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'days' => 'nullable|integer|min:1|max:36500',
        ]);

        return DB::transaction(function () use ($validated) {

            $plan = SubscriptionPlan::findOrFail(
                $validated['subscription_plan_id']
            );

            $days = (int) (
                $validated['days']
                ?? $plan->duration_days
                ?? 365
            );

            $subscription = Subscription::create([
                'subscription_plan_id' => $plan->id,
                'school_name' => $validated['school_name'],
                'contact_person' => $validated['contact_person'],
                'mobile' => $validated['mobile'],
                'email' => $validated['email'] ?? null,
                'status' => $plan->is_trial ? 'trial' : 'active',
                'amount' => $plan->is_trial ? 0 : $plan->yearly_price,
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addDays($days)->toDateString(),
                'is_trial' => $plan->is_trial,
                'auto_renew' => false,
            ]);

            if ($subscription->email) {
                Mail::to($subscription->email)
                    ->queue(
                        new SubscriptionActivatedMail($subscription)
                    );
            }

            $license = LicenseKey::create([
                'subscription_id' => $subscription->id,
                'license_key' => 'YT-' . strtoupper(Str::random(20)),
                'status' => 'active',
                'activated_at' => now(),
                'expires_at' => $subscription->ends_at,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully.',
                'data' => [
                    'subscription' => $subscription,
                    'license_key' => $license,
                ]
            ]);
        });
    }

    public function activate(Subscription $subscription)
    {
        $subscription->update([
            'status' => 'active'
        ]);

        if ($subscription->licenseKey) {
            $subscription->licenseKey->update([
                'status' => 'active',
                'activated_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription activated successfully.'
        ]);
    }

    public function extend(Request $request, Subscription $subscription)
    {
        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:36500'
        ]);

        $newExpiry = $subscription->ends_at
            ? \Carbon\Carbon::parse($subscription->ends_at)
            ->addDays((int)$validated['days'])
            : now()->addDays((int)$validated['days']);

        $subscription->update([
            'ends_at' => $newExpiry
        ]);

        if ($subscription->licenseKey) {
            $subscription->licenseKey->update([
                'expires_at' => $newExpiry
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription extended successfully.'
        ]);
    }

    public function updateStatus(Request $request, Subscription $subscription)
    {
        $validated = $request->validate([
            'status' => 'required|in:trial,active,expired,suspended,cancelled,pending_payment'
        ]);

        $subscription->update([
            'status' => $validated['status']
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully.'
        ]);
    }

    public function suspend(Subscription $subscription)
    {
        $subscription->update([
            'status' => 'suspended'
        ]);

        if ($subscription->licenseKey) {
            $subscription->licenseKey->update([
                'status' => 'suspended'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription suspended.'
        ]);
    }

    public function cancel(Subscription $subscription)
    {
        $subscription->update([
            'status' => 'cancelled'
        ]);

        if ($subscription->licenseKey) {
            $subscription->licenseKey->update([
                'status' => 'cancelled'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled.'
        ]);
    }

    public function currentStatus()
    {
        $userRole = auth()->user()->roleData?->slug
            ?? auth()->user()->role;

        $isSuperAdmin = in_array($userRole, ['superadmin', 'super_admin']);

        return response()->json([
            'success' => true,
            'data' => [
                'total' => Subscription::count(),
                'active' => Subscription::where('status', 'active')->count(),
                'trial' => Subscription::where('status', 'trial')->count(),
                'expired' => Subscription::where('status', 'expired')->count(),
                'suspended' => Subscription::where('status', 'suspended')->count(),
                'cancelled' => Subscription::where('status', 'cancelled')->count(),
                'pending_payment' => Subscription::where('status', 'pending_payment')->count(),
                'revenue' => Subscription::where('status', 'active')->sum('amount'),
                'expiring_soon' => Subscription::whereDate('ends_at', '<=', now()->addDays(7))
                    ->whereIn('status', ['trial', 'active'])
                    ->count(),

                'show_subscription_alert' => !$isSuperAdmin,
                'is_superadmin' => $isSuperAdmin,
            ],
        ]);
    }

    public function dashboard()
    {
        return $this->currentStatus();
    }

    public function destroy(Subscription $subscription)
    {
        $subscription->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subscription deleted successfully.'
        ]);
    }
}

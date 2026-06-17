<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DemoEnquiry;
use Illuminate\Http\Request;
use App\Models\DemoEnquiryRemark;
use App\Models\LicenseKey;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Mail\TrialStartedMail;
use Illuminate\Support\Facades\Mail;
use App\Mail\SaaSLoginCredentialsMail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DemoEnquiryController extends Controller
{
    public function index(Request $request)
    {
        $query = DemoEnquiry::query();

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

        $enquiries = $query
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $enquiries,
        ]);
    }

    public function show(DemoEnquiry $demoEnquiry)
    {
        return response()->json([
            'success' => true,
            'data' => $demoEnquiry->load([
                'remarks.user'
            ]),
        ]);
    }

    public function updateStatus(Request $request, DemoEnquiry $demoEnquiry)
    {
        $validated = $request->validate([
            'status' => 'required|in:new,contacted,demo_scheduled,demo_completed,trial_started,converted,rejected',
            'admin_note' => 'nullable|string',
        ]);

        $demoEnquiry->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Enquiry updated successfully.',
            'data' => $demoEnquiry->load([
                'remarks.user'
            ]),
        ]);
    }

    public function startDemo(Request $request, DemoEnquiry $demoEnquiry)
    {
        $validated = $request->validate([
            'admin_note' => 'nullable|string',
        ]);

        $demoEnquiry->update([
            'status' => 'demo_started',
            'admin_note' => $validated['admin_note'] ?? $demoEnquiry->admin_note,
            'demo_started_at' => now(),
            'demo_ends_at' => now()->addDays(15),
        ]);

        return response()->json([
            'success' => true,
            'message' => '15 days demo started successfully.',
            'data' => $demoEnquiry->load([
                'remarks.user'
            ]),
        ]);
    }

    public function addRemark(Request $request, DemoEnquiry $demoEnquiry)
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'remark' => 'required|string',
            'follow_up_at' => 'nullable|date',
        ]);

        $remark = DemoEnquiryRemark::create([
            'demo_enquiry_id' => $demoEnquiry->id,
            'user_id' => auth()->id(),
            'type' => $validated['type'],
            'remark' => $validated['remark'],
            'follow_up_at' => $validated['follow_up_at'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Remark added successfully.',
            'data' => $remark,
        ]);
    }

    public function updateFollowUp(Request $request, DemoEnquiry $demoEnquiry)
    {
        $validated = $request->validate([
            'follow_up_date' => 'required|date',
            'remarks' => 'nullable|string',
        ]);

        $demoEnquiry->update([
            'follow_up_date' => $validated['follow_up_date'],
            'last_contact_at' => now(),
        ]);

        DemoEnquiryRemark::create([
            'demo_enquiry_id' => $demoEnquiry->id,
            'user_id' => auth()->id(),
            'type' => 'follow_up',
            'remark' => $validated['remarks'] ?? 'Follow-up scheduled',
            'follow_up_at' => $validated['follow_up_date'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Follow-up updated successfully.',
        ]);
    }

    public function convertToSubscription(Request $request, DemoEnquiry $demoEnquiry)
    {
        $validated = $request->validate([
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'days' => 'nullable|integer|min:1|max:36500',
        ]);

        return DB::transaction(function () use ($demoEnquiry, $validated) {
            $plan = SubscriptionPlan::findOrFail($validated['subscription_plan_id']);
            $days = (int) ($validated['days'] ?? $plan->duration_days ?? 365);

            $startsAt = now();
            $endsAt = now()->addDays($days);

            $subscription = Subscription::create([
                'subscription_plan_id' => $plan->id,
                'demo_enquiry_id' => $demoEnquiry->id,
                'school_name' => $demoEnquiry->school_name,
                'contact_person' => $demoEnquiry->contact_person,
                'mobile' => $demoEnquiry->mobile,
                'email' => $demoEnquiry->email,
                'status' => $plan->is_trial ? 'trial' : 'active',
                'amount' => $plan->is_trial ? 0 : $plan->yearly_price,
                'starts_at' => $startsAt->toDateString(),
                'ends_at' => $endsAt->toDateString(),
                'is_trial' => $plan->is_trial,
                'auto_renew' => false,
            ]);

            $licenseKey = LicenseKey::create([
                'subscription_id' => $subscription->id,
                'license_key' => 'YT-' . strtoupper(Str::random(20)),
                'status' => 'active',
                'activated_at' => now(),
                'expires_at' => $endsAt,
            ]);

            $this->createSchoolAdminUser($subscription);

            $demoEnquiry->update([
                'status' => 'converted',
                'converted_subscription_id' => $subscription->id,
                'last_contact_at' => now(),
            ]);

            DemoEnquiryRemark::create([
                'demo_enquiry_id' => $demoEnquiry->id,
                'user_id' => auth()->id(),
                'type' => 'converted',
                'remark' => "Converted to {$plan->name} subscription.",
            ]);

            if ($subscription->email) {
                Mail::to($subscription->email)
                    ->queue(new TrialStartedMail($subscription->load(['plan', 'licenseKey'])));
            }

            return response()->json([
                'success' => true,
                'message' => 'Demo enquiry converted to subscription successfully.',
                'data' => [
                    'demo_enquiry' => $demoEnquiry->load(['remarks.user']),
                    'subscription' => $subscription->load(['plan', 'licenseKey']),
                    'license_key' => $licenseKey,
                ],
            ]);
        });
    }

    private function createSchoolAdminUser(Subscription $subscription): User
    {
        $plainPassword = Str::random(10);

        $schoolAdminRole = Role::whereIn('slug', [
            'school_admin',
            'admin',
        ])->first();

        $user = User::updateOrCreate(
            ['email' => $subscription->email],
            [
                'name' => $subscription->contact_person ?: $subscription->school_name,
                'contact' => $subscription->mobile,
                'role' => $schoolAdminRole?->slug ?? 'admin',
                'role_id' => $schoolAdminRole?->id,
                'password' => Hash::make($plainPassword),
                'login_enabled' => true,
                'is_active' => true,
                'password_change_required' => true,
            ]
        );

        if ($subscription->email) {
            Mail::to($subscription->email)
                ->send(new SaaSLoginCredentialsMail($user, $subscription->load('plan'), $plainPassword));
        }

        return $user;
    }
}

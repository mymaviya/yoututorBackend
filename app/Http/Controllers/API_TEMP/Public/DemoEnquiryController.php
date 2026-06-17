<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\DemoEnquiry;
use Illuminate\Http\Request;
use App\Models\SubscriptionPlan;
use App\Models\Subscription;
use App\Models\LicenseKey;
use Illuminate\Support\Str;
use App\Mail\DemoEnquiryConfirmationMail;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class DemoEnquiryController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'school_name' => 'required|max:255',
            'contact_person' => 'required|max:255',
            'mobile' => 'required|max:20',
            'email' => 'required|email|max:255',
            'school_type' => 'nullable|max:255',
            'interested_plan' => 'nullable|max:255',
            'message' => 'nullable',
        ]);

        $enquiry = DemoEnquiry::create($validated);

        if ($enquiry->email) {
            Mail::to($enquiry->email)
                ->send(new DemoEnquiryConfirmationMail($enquiry));
        }

        $superAdmins = User::whereHas('roleData', function ($q) {
            $q->whereIn('slug', ['superadmin', 'super_admin']);
        })
            ->orWhereIn('role', ['superadmin', 'super_admin'])
            ->get();

        foreach ($superAdmins as $superAdmin) {
            AppNotification::create([
                'user_id' => $superAdmin->id,
                'title' => 'New Demo Enquiry',
                'message' => "{$enquiry->school_name} submitted a demo enquiry.",
                'type' => 'demo_enquiry',
                'is_read' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Demo request submitted successfully.',
            'data' => $enquiry
        ]);
    }

    public function startDemo(Request $request, DemoEnquiry $demoEnquiry)
    {
        $validated = $request->validate([
            'admin_note' => 'nullable|string',
        ]);

        $trialPlan = SubscriptionPlan::where('slug', 'free-demo')->first();

        if (!$trialPlan) {
            return response()->json([
                'success' => false,
                'message' => 'Free demo plan not found. Please run SubscriptionPlanSeeder.',
            ], 404);
        }

        $startsAt = now();
        $endsAt = now()->addDays($trialPlan->trial_days ?: 15);

        $demoEnquiry->update([
            'status' => 'demo_started',
            'admin_note' => $validated['admin_note'] ?? $demoEnquiry->admin_note,
            'demo_started_at' => $startsAt,
            'demo_ends_at' => $endsAt,
        ]);

        $subscription = Subscription::updateOrCreate(
            [
                'demo_enquiry_id' => $demoEnquiry->id,
            ],
            [
                'subscription_plan_id' => $trialPlan->id,
                'school_name' => $demoEnquiry->school_name,
                'contact_person' => $demoEnquiry->contact_person,
                'mobile' => $demoEnquiry->mobile,
                'email' => $demoEnquiry->email,
                'status' => 'trial',
                'amount' => 0,                                                                                                                                                                                                                                                                                                                                                                                                                                  
                'starts_at' => $startsAt->toDateString(),
                'ends_at' => $endsAt->toDateString(),
                'is_trial' => true,
                'auto_renew' => false,                                                                                                                                                                  
            ]
        );

        $licenseKey = LicenseKey::updateOrCreate(
            [
                'subscription_id' => $subscription->id,
            ],
            [
                'license_key' => 'YT-' . strtoupper(Str::random(20)),
                'status' => 'active',
                'activated_at' => now(),
                'expires_at' => $endsAt,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => '15 days demo started and trial subscription activated successfully.',
            'data' => [
                'enquiry' => $demoEnquiry,
                'subscription' => $subscription,
                'license_key' => $licenseKey,
            ],
        ]);
    }
}

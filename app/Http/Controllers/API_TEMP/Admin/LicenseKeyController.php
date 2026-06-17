<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LicenseKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LicenseKeyController extends Controller
{
    public function index(Request $request)
    {
        $query = LicenseKey::with([
            'subscription.plan',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('license_key', 'like', "%{$search}%")
                    ->orWhere('domain', 'like', "%{$search}%")
                    ->orWhereHas('subscription', function ($subQuery) use ($search) {
                        $subQuery->where('school_name', 'like', "%{$search}%")
                            ->orWhere('contact_person', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $licenseKeys = $query
            ->latest()
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $licenseKeys,
        ]);
    }

    public function show(LicenseKey $licenseKey)
    {
        $licenseKey->load([
            'subscription.plan',
            'subscription.payments',
        ]);

        return response()->json([
            'success' => true,
            'data' => $licenseKey,
        ]);
    }

    public function updateStatus(Request $request, LicenseKey $licenseKey)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,inactive,expired,suspended,cancelled',
        ]);

        $licenseKey->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'License status updated successfully.',
            'data' => $licenseKey->load('subscription.plan'),
        ]);
    }

    public function extend(Request $request, LicenseKey $licenseKey)
    {
        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:36500',
        ]);

        return DB::transaction(function () use ($licenseKey, $validated) {
            $days = (int) $validated['days'];

            $currentEnd = $licenseKey->expires_at && $licenseKey->expires_at->greaterThan(now())
                ? $licenseKey->expires_at->copy()
                : now();

            $newEnd = $currentEnd->copy()->addDays($days);

            $licenseKey->update([
                'status' => 'active',
                'expires_at' => $newEnd,
            ]);

            if ($licenseKey->subscription) {
                $licenseKey->subscription->update([
                    'status' => 'active',
                    'ends_at' => $newEnd->toDateString(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'License extended successfully.',
                'data' => $licenseKey->load('subscription.plan'),
            ]);
        });
    }

    public function regenerate(LicenseKey $licenseKey)
    {
        $licenseKey->update([
            'license_key' => 'YT-' . strtoupper(Str::random(20)),
            'status' => 'active',
            'activated_at' => $licenseKey->activated_at ?: now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'License key regenerated successfully.',
            'data' => $licenseKey->load('subscription.plan'),
        ]);
    }
}
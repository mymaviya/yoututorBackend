<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SchoolProfileController extends Controller
{
    public function show(Request $request)
    {
        $subscription = $request->user()?->subscription;

        if (! $subscription) {
            return response()->json([
                'message' => 'Subscription not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatProfile($subscription),
        ]);
    }

    public function update(Request $request)
    {
        $subscription = $request->user()?->subscription;

        if (! $subscription) {
            return response()->json([
                'message' => 'Subscription not found.',
            ], 404);
        }

        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:255'],
            'school_address' => ['nullable', 'string', 'max:1000'],
            'school_phone' => ['nullable', 'string', 'max:50'],
            'school_email' => ['nullable', 'email', 'max:255'],
            'academic_session' => ['nullable', 'string', 'max:50'],
            'principal_name' => ['nullable', 'string', 'max:255'],
            'school_code' => ['nullable', 'string', 'max:100'],
            'affiliation_no' => ['nullable', 'string', 'max:100'],
            'school_website' => ['nullable', 'string', 'max:255'],
            'school_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('school_logo')) {
            if ($subscription->school_logo && Storage::disk('public')->exists($subscription->school_logo)) {
                Storage::disk('public')->delete($subscription->school_logo);
            }

            $data['school_logo'] = $request
                ->file('school_logo')
                ->store('school-logos', 'public');
        } else {
            unset($data['school_logo']);
        }

        $subscription->update($data);

        return response()->json([
            'success' => true,
            'message' => 'School profile updated successfully.',
            'data' => $this->formatProfile($subscription->fresh()),
        ]);
    }

    private function formatProfile($subscription): array
    {
        return [
            'id' => $subscription->id,
            'school_name' => $subscription->school_name,
            'school_address' => $subscription->school_address,
            'school_phone' => $subscription->school_phone ?? $subscription->mobile,
            'school_email' => $subscription->school_email ?? $subscription->email,
            'academic_session' => $subscription->academic_session,
            'principal_name' => $subscription->principal_name,
            'school_code' => $subscription->school_code,
            'affiliation_no' => $subscription->affiliation_no,
            'school_website' => $subscription->school_website,
            'school_logo' => $subscription->school_logo,
            'school_logo_url' => $subscription->school_logo
                ? asset('storage/' . $subscription->school_logo)
                : null,
        ];
    }
}

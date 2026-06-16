<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SubscriptionPlanController extends Controller
{
    public function index(Request $request)
    {
        $query = SubscriptionPlan::query();

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('slug', 'like', "%{$request->search}%");
            });
        }

        $plans = $query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:subscription_plans,slug',
            'monthly_display_price' => 'required|numeric|min:0',
            'yearly_price' => 'required|numeric|min:0',
            'yearly_saving' => 'nullable|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'trial_days' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string|max:255',
            'is_trial' => 'boolean',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['yearly_saving'] = $validated['yearly_saving'] ?? 0;
        $validated['trial_days'] = $validated['trial_days'] ?? 0;
        $validated['features'] = array_values(array_filter($validated['features'] ?? []));
        $validated['is_trial'] = $request->boolean('is_trial');
        $validated['is_popular'] = $request->boolean('is_popular');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        if ($validated['is_popular']) {
            SubscriptionPlan::where('is_popular', true)->update([
                'is_popular' => false,
            ]);
        }

        $plan = SubscriptionPlan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Subscription plan created successfully.',
            'data' => $plan,
        ], 201);
    }

    public function show(SubscriptionPlan $subscriptionPlan)
    {
        return response()->json([
            'success' => true,
            'data' => $subscriptionPlan,
        ]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('subscription_plans', 'slug')->ignore($subscriptionPlan->id),
            ],
            'monthly_display_price' => 'required|numeric|min:0',
            'yearly_price' => 'required|numeric|min:0',
            'yearly_saving' => 'nullable|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'trial_days' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string|max:255',
            'is_trial' => 'boolean',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['yearly_saving'] = $validated['yearly_saving'] ?? 0;
        $validated['trial_days'] = $validated['trial_days'] ?? 0;
        $validated['features'] = array_values(array_filter($validated['features'] ?? []));
        $validated['is_trial'] = $request->boolean('is_trial');
        $validated['is_popular'] = $request->boolean('is_popular');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        if ($validated['is_popular']) {
            SubscriptionPlan::where('id', '!=', $subscriptionPlan->id)
                ->where('is_popular', true)
                ->update([
                    'is_popular' => false,
                ]);
        }

        $subscriptionPlan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Subscription plan updated successfully.',
            'data' => $subscriptionPlan,
        ]);
    }

    public function destroy(SubscriptionPlan $subscriptionPlan)
    {
        if ($subscriptionPlan->subscriptions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This plan has subscriptions and cannot be deleted. You can deactivate it instead.',
            ], 422);
        }

        $subscriptionPlan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subscription plan deleted successfully.',
        ]);
    }
}
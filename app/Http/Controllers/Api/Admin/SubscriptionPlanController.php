<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SubscriptionPlanController extends Controller
{
    public function index(Request $request)
    {
        $query = SubscriptionPlan::with('featureItems');

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
            ->paginate((int) $request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedData($request);

        return DB::transaction(function () use ($request, $validated) {
            $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
            $validated['yearly_saving'] = $validated['yearly_saving'] ?? 0;
            $validated['trial_days'] = $validated['trial_days'] ?? 0;
            $validated['features'] = array_values(array_filter($validated['features'] ?? []));
            $validated['is_trial'] = $request->boolean('is_trial');
            $validated['is_popular'] = $request->boolean('is_popular');
            $validated['is_active'] = $request->boolean('is_active', true);
            $validated['sort_order'] = $validated['sort_order'] ?? 0;

            $featureItems = $validated['feature_items'] ?? [];
            unset($validated['feature_items']);

            if ($validated['is_popular']) {
                SubscriptionPlan::where('is_popular', true)->update([
                    'is_popular' => false,
                ]);
            }

            $plan = SubscriptionPlan::create($validated);

            $this->syncFeatureItems($plan, $featureItems);

            return response()->json([
                'success' => true,
                'message' => 'Subscription plan created successfully.',
                'data' => $plan->load('featureItems'),
            ], 201);
        });
    }

    public function show(SubscriptionPlan $subscriptionPlan)
    {
        return response()->json([
            'success' => true,
            'data' => $subscriptionPlan->load('featureItems'),
        ]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $validated = $this->validatedData($request, $subscriptionPlan->id);

        return DB::transaction(function () use ($request, $subscriptionPlan, $validated) {
            $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
            $validated['yearly_saving'] = $validated['yearly_saving'] ?? 0;
            $validated['trial_days'] = $validated['trial_days'] ?? 0;
            $validated['features'] = array_values(array_filter($validated['features'] ?? []));
            $validated['is_trial'] = $request->boolean('is_trial');
            $validated['is_popular'] = $request->boolean('is_popular');
            $validated['is_active'] = $request->boolean('is_active');
            $validated['sort_order'] = $validated['sort_order'] ?? 0;

            $featureItems = $validated['feature_items'] ?? [];
            unset($validated['feature_items']);

            if ($validated['is_popular']) {
                SubscriptionPlan::where('id', '!=', $subscriptionPlan->id)
                    ->where('is_popular', true)
                    ->update([
                        'is_popular' => false,
                    ]);
            }

            $subscriptionPlan->update($validated);

            $this->syncFeatureItems($subscriptionPlan, $featureItems);

            return response()->json([
                'success' => true,
                'message' => 'Subscription plan updated successfully.',
                'data' => $subscriptionPlan->fresh()->load('featureItems'),
            ]);
        });
    }

    public function destroy(SubscriptionPlan $subscriptionPlan)
    {
        if ($subscriptionPlan->subscriptions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This plan has subscriptions and cannot be deleted. You can deactivate it instead.',
            ], 422);
        }

        $subscriptionPlan->featureItems()->delete();
        $subscriptionPlan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subscription plan deleted successfully.',
        ]);
    }

    private function validatedData(Request $request, ?int $planId = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('subscription_plans', 'slug')->ignore($planId),
            ],
            'monthly_display_price' => 'required|numeric|min:0',
            'yearly_price' => 'required|numeric|min:0',
            'yearly_saving' => 'nullable|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'trial_days' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string|max:255',
            'feature_items' => 'nullable|array',
            'feature_items.*.feature_key' => 'required_with:feature_items|string|max:100',
            'feature_items.*.is_enabled' => 'nullable|boolean',
            'is_trial' => 'boolean',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);
    }

    private function syncFeatureItems(SubscriptionPlan $plan, array $featureItems): void
    {
        $normalized = collect($featureItems)
            ->filter(fn ($item) => ! empty($item['feature_key']))
            ->map(function ($item) {
                return [
                    'feature_key' => trim((string) $item['feature_key']),
                    'is_enabled' => (bool) ($item['is_enabled'] ?? true),
                ];
            })
            ->unique('feature_key')
            ->values();

        $incomingKeys = $normalized->pluck('feature_key')->all();

        if (! empty($incomingKeys)) {
            $plan->featureItems()
                ->whereNotIn('feature_key', $incomingKeys)
                ->delete();
        } else {
            $plan->featureItems()->delete();
        }

        foreach ($normalized as $item) {
            $plan->featureItems()->updateOrCreate(
                ['feature_key' => $item['feature_key']],
                ['is_enabled' => $item['is_enabled']]
            );
        }
    }
}

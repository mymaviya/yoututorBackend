<?php

namespace App\Models\Concerns;

use App\Models\Scopes\SubscriptionScope;

trait BelongsToSubscription
{
    protected static function bootBelongsToSubscription(): void
    {
        static::addGlobalScope(new SubscriptionScope());

        static::creating(function ($model) {
            if (app()->runningInConsole()) {
                return;
            }

            if (! auth()->check()) {
                return;
            }

            $user = auth()->user();

            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return;
            }

            if (! $user?->subscription_id) {
                return;
            }

            if (! empty($model->subscription_id)) {
                return;
            }

            $model->subscription_id = $user->subscription_id;
        });
    }

    public function scopeWithoutSubscriptionScope($query)
    {
        return $query->withoutGlobalScope(SubscriptionScope::class);
    }

    public function scopeForCurrentSubscription($query)
    {
        if (app()->runningInConsole() || ! auth()->check()) {
            return $query;
        }

        $user = auth()->user();

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return $query;
        }

        if (! $user?->subscription_id) {
            return $query;
        }

        return $query->where($query->getModel()->getTable() . '.subscription_id', $user->subscription_id);
    }
}

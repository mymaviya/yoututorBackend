<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class SubscriptionScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        if (! auth()->check()) {
            return;
        }

        $user = auth()->user();

        if ($this->isSuperAdmin($user)) {
            return;
        }

        if (! $user?->subscription_id) {
            return;
        }

        $builder->where(
            $model->getTable() . '.subscription_id',
            $user->subscription_id
        );
    }

    private function isSuperAdmin($user): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin')) {
            return (bool) $user->isSuperAdmin();
        }

        return in_array($user->role ?? null, [
            'superadmin',
            'super_admin',
            'Super Admin',
        ], true);
    }
}
